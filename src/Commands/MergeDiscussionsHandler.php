<?php

/*
 * This file is part of fof/merge-discussions.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\MergeDiscussions\Commands;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Extension\ExtensionManager;
use Flarum\Lock\Event\DiscussionWasLocked;
use Flarum\Lock\Event\DiscussionWasUnlocked;
use Flarum\Post\Post;
use Flarum\User\User;
use Flarum\User\UserRepository;
use FoF\MergeDiscussions\Events\DiscussionWasMerged;
use FoF\MergeDiscussions\Events\MergingDiscussions;
use FoF\MergeDiscussions\Models\Redirection;
use FoF\MergeDiscussions\Validators\MergeDiscussionValidator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MergeDiscussionsHandler
{
    /**
     * @var ConnectionResolverInterface
     */
    protected $db;

    /**
     * @var UserRepository
     */
    protected $users;

    /**
     * @var DiscussionRepository
     */
    protected $discussions;

    /**
     * @var Dispatcher
     */
    protected $events;

    /**
     * @var MergeDiscussionValidator
     */
    protected $validator;

    /**
     * @var ExtensionManager
     */
    protected $extensions;

    public function __construct(
        ConnectionResolverInterface $db,
        UserRepository $users,
        DiscussionRepository $discussions,
        Dispatcher $events,
        MergeDiscussionValidator $validator,
        ExtensionManager $extensions
    ) {
        $this->db = $db;
        $this->users = $users;
        $this->discussions = $discussions;
        $this->events = $events;
        $this->validator = $validator;
        $this->extensions = $extensions;
    }

    /**
     * Runs the process in a transaction.
     * Exceptions will always result in no changes.
     *
     * @throws \Flarum\User\Exception\PermissionDeniedException
     * @throws \Illuminate\Validation\ValidationException
     *
     * @return Discussion
     */
    public function handle(MergeDiscussions $command): Discussion
    {
        try {
            $this->db->connection()->beginTransaction();
            $discussion = $this->process($command);
            if ($command->merge) {
                $this->db->connection()->commit();
            }
        } catch (\Exception $e) {
            $this->db->connection()->rollBack();

            throw $e;
        }

        return $discussion;
    }

    public function process(MergeDiscussions $command)
    {
        $discussion = $this->discussions->findOrFail($command->discussionId);

        $actor = $command->actor;

        $actor->assertCan('merge', $discussion);

        $postIds = $command->ids;
        $method = $command->ordering; // can be either 'date' or 'suffix'; default is 'date'

        $this->validator->assertValid([
            'discussion_id' => $discussion->id,
            'posts'         => $postIds,
        ]);

        /** @var EloquentCollection $discussions */
        $discussions = Discussion::query()
            ->findMany($command->ids);

        /** @var EloquentCollection $posts */
        $posts = Post::query()
            ->whereIn('discussion_id', $command->ids)
            ->orderBy('created_at')
            ->get();

        $this->validator->assertValid([
            'posts' => $posts->toArray(),
        ]);

        $this->events->dispatch(new MergingDiscussions($actor, $posts, $discussion, $discussions, $method));

        $result = $this->useMethod($method, $discussion, $posts);

        $this->createRedirectsFromMergedDiscussions($discussion, $discussions);

        $this->unlockTargetDiscussion($actor, $discussion);

        $this->events->dispatch(new DiscussionWasMerged($actor, $posts, $discussion, $discussions));

        return $result;
    }

    protected function useMethod(string $method, Discussion $discussion, EloquentCollection $posts): Discussion
    {
        return $this->$method($discussion, $posts);
    }

    protected function lockDiscussion(Discussion $discussion): void
    {
        if ($this->extensions->isEnabled('flarum-lock')) {
            /** @phpstan-ignore-next-line */
            $discussion->is_locked = true;
            $discussion->save();

            //$this->events->dispatch(new DiscussionWasLocked($discussion, $actor));
        }
    }

    /**
     * If `flarum/lock` is enabled, unlock the target discussion after merging.
     *
     * @param User       $actor
     * @param Discussion $discussion
     *
     * @return void
     */
    protected function unlockTargetDiscussion(User $actor, Discussion $discussion): void
    {
        if ($this->extensions->isEnabled('flarum-lock')) {
            /** @phpstan-ignore-next-line */
            $discussion->is_locked = false;
            $discussion->save();

            //$this->events->dispatch(new DiscussionWasUnlocked($discussion, $actor));
        }
    }

    /**
     * Creates redirects from merged discussions, and deletes the old Discussion entities.
     *
     * @param Discussion         $targetDiscussion
     * @param EloquentCollection $discussions
     *
     * @return void
     */
    protected function createRedirectsFromMergedDiscussions(Discussion $targetDiscussion, EloquentCollection $discussions): void
    {
        $discussions->each(function (Discussion $discussion) use ($targetDiscussion) {
            Redirection::build($discussion, $targetDiscussion);

            $discussion->delete();
        });
    }

    protected function date(Discussion $discussion, EloquentCollection $posts): Discussion
    {
        $db = $this->db->connection();

        // Create number gaps in discussion
        $selectCreatedAt = $db->query()
            ->select('created_at')
            ->from('posts')
            ->whereIn('id', $posts->pluck('id'));

        $selectCount = $db->query()
            ->mergeBindings($selectCreatedAt)
            ->selectRaw('COUNT(created_at) as count')
            ->from($db->raw("({$selectCreatedAt->toSql()}) as sp"))
            ->whereColumn('posts.created_at', '>=', $db->raw('sp.created_at'));

        $db->table('posts')
            ->mergeBindings($selectCount)
            ->where('discussion_id', $discussion->id)
            ->orderBy('number', 'desc')
            ->update(['number' => $db->raw("number + ({$selectCount->toSql()})")]);

        // To fill the gaps with the new posts,
        // we query the posts that will be ordered right before the new ones,
        // so that we can calculate the numbers of the new posts.
        $max = $db->query()
            ->select('number')
            ->from('posts')
            ->where('discussion_id', $discussion->id)
            ->whereColumn($db->raw('r.created_at'), '>', 'posts.created_at')
            ->orderBy('number', 'desc')
            ->limit(1);

        $numbers = $db->query()
            ->mergeBindings($max)
            ->mergeBindings($selectCreatedAt)
            ->selectRaw("DISTINCT ({$max->toSql()}) as number")
            ->from($db->raw("({$selectCreatedAt->toSql()}) as r"))
            ->get();

        // Now we get the checkpoints and use them to calculate new post numbers
        $checkpointPosts = Post::query()
            ->where('discussion_id', $discussion->id)
            ->whereIn('number', $numbers->pluck('number')->toArray())
            ->get();

        $merged = $checkpointPosts->merge($posts)->sortBy('created_at')->values();

        $merged->map(function (Post $post, int $key) use ($merged, $checkpointPosts, $discussion) {
            $prev = $merged[$key - 1] ?? null;

            if ($prev && !$checkpointPosts->firstWhere('id', $post->id)) {
                $post->number = $prev->number + 1;
                $post->discussion_id = $discussion->id;

                $post->save();
            }

            return $post;
        });

        if (Carbon::parse($discussion->firstPost->created_at) > Carbon::parse($posts->first()->created_at)) {
            $discussion->setFirstPost($posts->first());
        }

        $discussion->refreshCommentCount();
        $discussion->refreshParticipantCount();
        $discussion->refreshLastPost();

        $discussion->save();

        return $discussion;
    }

    /**
     * Pushes the merged posts to the end of the discussion.
     *
     * @param Discussion               $discussion
     * @param EloquentCollection<Post> $posts
     *
     * @return Discussion
     */
    protected function suffix(Discussion $discussion, EloquentCollection $posts): Discussion
    {
        $numberDifference = $discussion->posts()->max('number') - $posts->first()->number + 1;
        $posts->toQuery()->update([
            'discussion_id' => $discussion->id,
            /** @phpstan-ignore-next-line */
            'number' => $this->db->raw("number + $numberDifference"),
        ]);

        $discussion->refreshCommentCount();
        $discussion->refreshParticipantCount();
        $discussion->refreshLastPost();

        $discussion->save();

        return $discussion;
    }
}
