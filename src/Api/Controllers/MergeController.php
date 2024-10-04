<?php

/*
 * This file is part of fof/merge-discussions.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\MergeDiscussions\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use FoF\MergeDiscussions\Jobs\MergeDiscussionsJob;
use FoF\MergeDiscussions\Validators\MergeDiscussionValidator;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class MergeController extends AbstractShowController
{
    /**
     * The serializer instance for this request.
     *
     * @var string
     */
    public $serializer = DiscussionSerializer::class;

    /**
     * @var Dispatcher
     */
    protected $bus;

    /**
     * @var MergeDiscussionValidator
     */
    protected $validator;

    /**
     * @var Queue
     */
    protected $queue;

    public function __construct(Dispatcher $bus, MergeDiscussionValidator $validator, Queue $queue)
    {
        $this->bus = $bus;
        $this->validator = $validator;
        $this->queue = $queue;
    }

    /**
     * Get the data to be serialized and assigned to the response document.
     *
     * @param ServerRequestInterface $request
     * @param Document               $document
     *
     * @return mixed
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $discussion = Discussion::findOrFail(Arr::get($request->getQueryParams(), 'id'));

        $actor->assertCan('merge', $discussion);

        $ids = Arr::get($request->getParsedBody(), 'ids');
        $ordering = Arr::get($request->getParsedBody(), 'ordering');

        $this->validator->assertValid([
            'discussion_id'       => $discussion->id,
            'merging_discussions' => $ids,
        ]);

        $this->queue->push(new MergeDiscussionsJob($actor, $discussion->id, $ids, $ordering));

        return $discussion;
    }
}
