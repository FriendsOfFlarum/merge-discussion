<?php

/*
 * This file is part of fof/merge-discussions.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MergeDiscussions\Api\Commands;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\User\AssertPermissionTrait;
use Flarum\User\UserRepository;
use FoF\MergeDiscussions\Events\DiscussionWasMerged;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Arr;

class MergeDiscussionHandler
{
    use AssertPermissionTrait;

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
     * @param UserRepository       $users
     * @param DiscussionRepository $discussions
     * @param Dispatcher           $events
     */
    public function __construct(
        UserRepository $users,
        DiscussionRepository $discussions,
        Dispatcher $events
    ) {
        $this->users = $users;
        $this->discussions = $discussions;
        $this->events = $events;
    }

    public function handle(MergeDiscussion $command)
    {
        $discussion = $this->discussions->findOrFail($command->discussionId);
        $discussions = [];
        $mergedPosts = [];

        $this->assertCan($command->actor, 'merge', $discussion);

        $posts = $discussion->posts;

        foreach ($command->ids as $id) {
            $d = Discussion::find($id);

            if ($d == null) {
                continue;
            }

            $discussions[] = $d;

            $posts = $posts->merge(
                $mergedPosts[] = $d->posts
            );
        }

        $number = 0;

        $posts->sortBy('created_at')->each(function ($post, $i) use ($discussion, &$number) {
            $number++;

            $post->number = $number;
            $post->discussion_id = $discussion->id;

            $discussion->posts[$i] = $post;
        });

        // @see https://github.com/FriendsOfFlarum/merge-discussions/issues/5
        $discussion->setRelation('posts', $discussion->posts->sortByDesc('number'));

        $discussion->post_number_index = $number;

        if ($command->merge) {
            $discussion->push();

            $discussion->refreshCommentCount()
                ->refreshParticipantCount()
                ->refreshLastPost()
                ->setFirstPost($discussion->posts->first())
                ->save();

            foreach ($discussions as $d) {
                $d->delete();
            }

            $this->events->dispatch(
                new DiscussionWasMerged($command->actor, Arr::flatten($mergedPosts), $discussion, $discussions)
            );
        }

        return $discussion;
    }
}
