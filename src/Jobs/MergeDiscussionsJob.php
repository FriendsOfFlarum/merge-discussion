<?php

/*
 * This file is part of fof/merge-discussions.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\MergeDiscussions\Jobs;

use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use FoF\MergeDiscussions\Commands\MergeDiscussions;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

class MergeDiscussionsJob extends AbstractJob
{
    protected $actor;

    protected $discussionId;

    protected $ids;

    protected $ordering;

    protected $merge;

    public function __construct(User $actor, int $discussionId, array $ids, string $ordering = 'date', bool $merge = true)
    {
        $this->actor = $actor;
        $this->discussionId = (int) $discussionId;
        $this->ids = $ids;
        $this->ordering = $ordering;
        $this->merge = $merge;
    }

    public function handle(Dispatcher $bus, ExtensionManager $extensions, EventDispatcher $events, NotificationSyncer $notifications)
    {
        /** @var Discussion $discussion */
        $discussion = $bus->dispatch(new MergeDiscussions($this->actor, $this->discussionId, $this->ids, $this->ordering, $this->merge));

        // TODO: send notification on merge completion
    }
}
