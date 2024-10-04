<?php

/*
 * This file is part of fof/merge-discussions.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\MergeDiscussions\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class MergeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-lock');
        $this->extension('fof-merge-discussions');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', 'is_email_confirmed' => true],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Discussion 1', 'comment_count' => 5, 'user_id' => 1, 'created_at' => Carbon::now()->subDays(5), 'first_post_id' => 1],
                ['id' => 2, 'title' => 'Discussion 2', 'comment_count' => 5, 'user_id' => 2, 'created_at' => Carbon::now()->subDays(4), 'first_post_id' => 2],
                ['id' => 3, 'title' => 'Discussion 3', 'comment_count' => 5, 'user_id' => 2, 'created_at' => Carbon::now()->subDays(3), 'first_post_id' => 3],
                ['id' => 4, 'title' => 'Discussion 4', 'comment_count' => 5, 'user_id' => 3, 'created_at' => Carbon::now()->subDays(2), 'first_post_id' => 4],
            ],
            'posts' => [
                // Existing first posts for each discussion, spaced 4 hours apart
                ['id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => 'Post 1 in Discussion 1', 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now()->subDays(5)->subHours(4)],
                ['id' => 2, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 1 in Discussion 2', 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now()->subDays(4)->subHours(4)],
                ['id' => 3, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 1 in Discussion 3', 'discussion_id' => 3, 'number' => 1, 'created_at' => Carbon::now()->subDays(3)->subHours(4)],
                ['id' => 4, 'user_id' => 3, 'type' => 'comment', 'content' => 'Post 1 in Discussion 4', 'discussion_id' => 4, 'number' => 1, 'created_at' => Carbon::now()->subDays(2)->subHours(4)],
                // Additional posts for Discussion 1, interleaved with Discussion 2
                ['id' => 5, 'user_id' => 1, 'type' => 'comment', 'content' => 'Post 2 in Discussion 1', 'discussion_id' => 1, 'number' => 2, 'created_at' => Carbon::now()->subDays(4)->subHours(3)],
                ['id' => 6, 'user_id' => 1, 'type' => 'comment', 'content' => 'Post 3 in Discussion 1', 'discussion_id' => 1, 'number' => 3, 'created_at' => Carbon::now()->subDays(3)->subHours(2)],
                ['id' => 7, 'user_id' => 1, 'type' => 'comment', 'content' => 'Post 4 in Discussion 1', 'discussion_id' => 1, 'number' => 4, 'created_at' => Carbon::now()->subDays(2)->subHour()],
                ['id' => 8, 'user_id' => 1, 'type' => 'comment', 'content' => 'Post 5 in Discussion 1', 'discussion_id' => 1, 'number' => 5, 'created_at' => Carbon::now()->subDays(1)],
                // Additional posts for Discussion 2, interleaved with Discussion 1 and 3
                ['id' => 9, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 2 in Discussion 2', 'discussion_id' => 2, 'number' => 2, 'created_at' => Carbon::now()->subDays(4)->subHours(2)],
                ['id' => 10, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 3 in Discussion 2', 'discussion_id' => 2, 'number' => 3, 'created_at' => Carbon::now()->subDays(3)->subHour()],
                ['id' => 11, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 4 in Discussion 2', 'discussion_id' => 2, 'number' => 4, 'created_at' => Carbon::now()->subDays(2)],
                ['id' => 12, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 5 in Discussion 2', 'discussion_id' => 2, 'number' => 5, 'created_at' => Carbon::now()->subDays(1)->subHours(3)],
                // Additional posts for Discussion 3, interleaved with Discussion 2 and 4
                ['id' => 13, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 2 in Discussion 3', 'discussion_id' => 3, 'number' => 2, 'created_at' => Carbon::now()->subDays(3)->subHours(3)],
                ['id' => 14, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 3 in Discussion 3', 'discussion_id' => 3, 'number' => 3, 'created_at' => Carbon::now()->subDays(2)->subHours(2)],
                ['id' => 15, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 4 in Discussion 3', 'discussion_id' => 3, 'number' => 4, 'created_at' => Carbon::now()->subDay()->subHours(1)],
                ['id' => 16, 'user_id' => 2, 'type' => 'comment', 'content' => 'Post 5 in Discussion 3', 'discussion_id' => 3, 'number' => 5, 'created_at' => Carbon::now()],
                // Additional posts for Discussion 4, interleaved with Discussion 3
                ['id' => 17, 'user_id' => 3, 'type' => 'comment', 'content' => 'Post 2 in Discussion 4', 'discussion_id' => 4, 'number' => 2, 'created_at' => Carbon::now()->subDays(2)->subHours(3)],
                ['id' => 18, 'user_id' => 3, 'type' => 'comment', 'content' => 'Post 3 in Discussion 4', 'discussion_id' => 4, 'number' => 3, 'created_at' => Carbon::now()->subDay()->subHours(2)],
                ['id' => 19, 'user_id' => 3, 'type' => 'comment', 'content' => 'Post 4 in Discussion 4', 'discussion_id' => 4, 'number' => 4, 'created_at' => Carbon::now()->subHours(1)],
                ['id' => 20, 'user_id' => 3, 'type' => 'comment', 'content' => 'Post 5 in Discussion 4', 'discussion_id' => 4, 'number' => 5, 'created_at' => Carbon::now()->subHour()],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4],
            ],
            'group_permission' => [
                ['group_id' => 4, 'permission' => 'discussion.merge'],
            ],
        ]);
    }

    /**
     * @test
     */
    public function cannot_merge_discussions_without_data()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions/1/merge', [
                'json'            => [],
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('errors', $data);
        $this->assertCount(1, $data['errors']);
        $this->assertEquals('/data/attributes/merging_discussions', $data['errors'][0]['source']['pointer']);
    }

    /**
     * @test
     */
    public function cannot_preview_discussion_merge_without_data()
    {
        $response = $this->send(
            $this->request('GET', '/api/discussions/1/merge', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('errors', $data);
        $this->assertCount(1, $data['errors']);
        $this->assertEquals('/data/attributes/merging_discussions', $data['errors'][0]['source']['pointer']);
    }

    /**
     * @test
     */
    public function unauthorized_user_cannot_merge_discussions()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions/1/merge', [
                'json' => [
                    'ids' => [2],
                ],
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    // TODO: Fix this test
    // /**
    //  * @test
    //  */
    // public function unauthorized_user_cannot_preview_discussion_merge()
    // {
    //     // Build the query parameters string
    //     $queryParams = http_build_query([
    //         'ids' => [2],
    //         'ordering' => 'date'
    //     ]);

    //     // Append the query parameters to the URL
    //     $response = $this->send(
    //         $this->request('GET', '/api/discussions/1/merge?' . $queryParams, [
    //             'authenticatedAs' => 2,
    //         ])
    //     );

    //     $this->assertEquals(403, $response->getStatusCode());
    // }

    /**
     * @test
     *
     * @dataProvider invalidDiscussionData
     */
    public function cannot_merge_discussion_with_invalid_data(int $to, int $from, int $statusCode, string $pointer = null)
    {
        $response = $this->send(
            $this->request('POST', "/api/discussions/$to/merge", [
                'json' => [
                    'ids' => [$from],
                ],
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals($statusCode, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('errors', $data);
        $this->assertCount(1, $data['errors']);

        if ($pointer) {
            $this->assertEquals('/data/attributes/'.$pointer, $data['errors'][0]['source']['pointer']);
        }
    }

    public function discussionMergeData(): array
    {
        return [
            [1, 2], // Merging newer discussion into older
            [2, 1], // Merging older discussion into newer
        ];
    }

    public function invalidDiscussionData(): array
    {
        return [
            [1, 100, 422, 'merging_discussions'],
            [100, 1, 404],
        ];
    }

    /**
     * @test
     *
     * @dataProvider discussionMergeData
     */
    public function can_merge_discussions_by_date(int $to, int $from)
    {
        $response = $this->send(
            $this->request('POST', "/api/discussions/$to/merge", [
                'json' => [
                    'ids'      => [$from],
                    'ordering' => 'date',
                ],
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($to, $data['data']['id']);

        $discussion = Discussion::find($to);

        $this->assertEquals(10, $discussion->comment_count, 'Unexpected comment count');
        $this->assertEquals(11, $discussion->posts->count(), 'Unexpected post count');
        $this->assertEquals(2, $discussion->participant_count);
        $this->assertFalse($discussion->is_locked, 'Discussion was not unlocked after merge');

        $posts = $discussion->posts;

        foreach ($posts as $post) {
            if ($post !== $posts->last()) {
                $this->assertEquals('comment', $post->type);
            }
        }

        $this->assertEquals(11, $posts->count());

        // Check the posts were ordered as expected by date/time
        $this->assertPostAttrs($posts[0], 1, 1, 1);
        $this->assertPostAttrs($posts[1], 2, 1, 2, $posts[0]);
        $this->assertPostAttrs($posts[2], 1, 2, 3, $posts[1]);
        $this->assertPostAttrs($posts[3], 2, 2, 4, $posts[2]);
        $this->assertPostAttrs($posts[4], 1, 3, 5, $posts[3]);
        $this->assertPostAttrs($posts[5], 2, 3, 6, $posts[4]);
        $this->assertPostAttrs($posts[6], 1, 4, 7, $posts[5]);
        $this->assertPostAttrs($posts[7], 2, 4, 8, $posts[6]);
        $this->assertPostAttrs($posts[8], 2, 5, 9, $posts[7]);
        $this->assertPostAttrs($posts[9], 1, 5, 10, $posts[8]);

        $this->assertEquals('discussionMerged', $posts[10]->type);
        $this->assertEquals(11, $posts[10]->number);

        $this->assertRedirection($from, $to);
    }

    /**
     * @test
     *
     * @dataProvider discussionMergeData
     */
    public function can_merge_discussions_by_suffix(int $to, int $from)
    {
        $response = $this->send(
            $this->request('POST', "/api/discussions/$to/merge", [
                'json' => [
                    'ids'      => [$from],
                    'ordering' => 'suffix',
                ],
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($to, $data['data']['id']);

        $discussion = Discussion::find($to);

        $this->assertEquals(10, $discussion->comment_count);
        $this->assertEquals(2, $discussion->participant_count);

        $posts = $discussion->posts()->get();

        foreach ($posts as $post) {
            if ($post !== $posts->last()) {
                $this->assertEquals('comment', $post->type);
            }
        }

        $this->assertEquals(11, $posts->count());

        // check the posts were ordered as expected

        $this->assertPostAttrs($posts[0], $to, 1, 1);
        $this->assertPostAttrs($posts[1], $to, 2, 2);
        $this->assertPostAttrs($posts[2], $to, 3, 3);
        $this->assertPostAttrs($posts[3], $to, 4, 4);
        $this->assertPostAttrs($posts[4], $to, 5, 5);

        // $this->assertEquals('discussionLocked', $posts[5]->type);
        // $this->assertEquals(6, $posts[5]->number);

        $this->assertPostAttrs($posts[5], $from, 1, 6);
        $this->assertPostAttrs($posts[6], $from, 2, 7);
        $this->assertPostAttrs($posts[7], $from, 3, 8);
        $this->assertPostAttrs($posts[8], $from, 4, 9);
        $this->assertPostAttrs($posts[9], $from, 5, 10);

        $this->assertEquals('discussionMerged', $posts[10]->type);
        $this->assertEquals(11, $posts[10]->number);

        $this->assertRedirection($from, $to);
    }

    protected function assertPostAttrs(Post $post, int $expectedDiscussion, int $expectedPostContentNumber, int $expectedNumber, Post $previousPost = null): void
    {
        $this->assertEquals("Post $expectedPostContentNumber in Discussion $expectedDiscussion", $post->content, "Post content incorrect: $post->content");
        $this->assertEquals('comment', $post->type, "Post type incorrect: $post->type");
        $this->assertEquals($expectedNumber, $post->number, "Post number incorrect: $post->number");

        if ($previousPost) {
            $this->assertTrue(Carbon::parse($post->created_at) > Carbon::parse($previousPost->created_at), "Post $post->id created_at ($post->created_at) is after previous post $previousPost->id created_at ($previousPost->created_at)");
        }
    }

    protected function assertRedirection(int $from, int $to, int $statusCode = 301): void
    {
        $response = $this->send(
            $this->request('GET', "/d/$from", [])
        );

        $this->assertEquals($statusCode, $response->getStatusCode());
        $this->assertEquals("/d/$to", $response->getHeader('Location')[0]);
    }

    /**
     * @test
     */
    public function can_merge_multiple_discussions_by_date()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions/1/merge', [
                'json' => [
                    'ids'      => [2, 3],
                    'ordering' => 'date',
                ],
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertEquals(1, $data['data']['id']);

        $discussion = Discussion::find(1);

        $this->assertEquals(15, $discussion->comment_count);
        $this->assertEquals(2, $discussion->participant_count);

        $posts = $discussion->posts()->orderBy('created_at', 'asc')->get();

        $this->assertEquals(16, $posts->count());

        foreach ($posts as $post) {
            if ($post !== $posts->last()) {
                $this->assertEquals('comment', $post->type);
            }
        }

        // Check the posts were ordered as expected by date/time

        $this->assertPostAttrs($posts[0], 1, 1, 1);
        $this->assertPostAttrs($posts[1], 2, 1, 2);
        $this->assertPostAttrs($posts[2], 1, 2, 3);
        $this->assertPostAttrs($posts[3], 2, 2, 4);
        $this->assertPostAttrs($posts[4], 3, 1, 5);
        $this->assertPostAttrs($posts[5], 3, 2, 6);
        $this->assertPostAttrs($posts[6], 1, 3, 7);
        $this->assertPostAttrs($posts[7], 2, 3, 8);
        $this->assertPostAttrs($posts[8], 3, 3, 9);
        $this->assertPostAttrs($posts[9], 1, 4, 10);
        $this->assertPostAttrs($posts[10], 2, 4, 11);
        $this->assertPostAttrs($posts[11], 2, 5, 12);
        $this->assertPostAttrs($posts[12], 3, 4, 13);
        $this->assertPostAttrs($posts[13], 1, 5, 14);
        $this->assertPostAttrs($posts[14], 3, 5, 15);

        $this->assertEquals('discussionMerged', $posts[15]->type);
        $this->assertEquals(16, $posts[15]->number);

        $this->assertRedirection(2, 1);
        $this->assertRedirection(3, 1);
    }

    // TODO: Fix this test
    // /**
    //  * @test
    //  */
    // public function can_merge_multiple_discussions_by_suffix()
    // {
    //     $response = $this->send(
    //         $this->request('POST', "/api/discussions/1/merge", [
    //             'json' => [
    //                 'ids'      => [2, 3],
    //                 'ordering' => 'suffix',
    //             ],
    //             'authenticatedAs' => 3,
    //         ])
    //     );

    //     $this->assertEquals(200, $response->getStatusCode());

    //     $data = json_decode($response->getBody()->getContents(), true);

    //     $this->assertArrayHasKey('data', $data);
    //     $this->assertEquals(1, $data['data']['id']);

    //     $discussion = Discussion::find(1);

    //     $this->assertEquals(15, $discussion->comment_count);
    //     $this->assertEquals(2, $discussion->participant_count);

    //     $posts = $discussion->posts()->get();

    //     $this->assertEquals(16, $posts->count());

    //     // check the posts were ordered as expected

    //     $this->assertEquals("Post 1 in Discussion 1", $posts[0]->content);
    //     $this->assertEquals('comment', $posts[0]->type);
    //     $this->assertEquals(1, $posts[0]->number);

    //     $this->assertEquals("Post 2 in Discussion 1", $posts[1]->content);
    //     $this->assertEquals('comment', $posts[1]->type);
    //     $this->assertEquals(2, $posts[1]->number);

    //     $this->assertEquals("Post 3 in Discussion 1", $posts[2]->content);
    //     $this->assertEquals('comment', $posts[2]->type);
    //     $this->assertEquals(3, $posts[2]->number);

    //     $this->assertEquals("Post 4 in Discussion 1", $posts[3]->content);
    //     $this->assertEquals('comment', $posts[3]->type);
    //     $this->assertEquals(4, $posts[3]->number);

    //     $this->assertEquals("Post 5 in Discussion 1", $posts[4]->content);
    //     $this->assertEquals('comment', $posts[4]->type);
    //     $this->assertEquals(5, $posts[4]->number);

    //     $this->assertEquals("Post 1 in Discussion 2", $posts[5]->content);
    //     $this->assertEquals('comment', $posts[5]->type);
    //     $this->assertEquals(6, $posts[5]->number);

    //     $this->assertEquals("Post 2 in Discussion 2", $posts[6]->content);
    //     $this->assertEquals('comment', $posts[6]->type);
    //     $this->assertEquals(7, $posts[6]->number);

    //     $this->assertEquals("Post 3 in Discussion 2", $posts[7]->content);
    //     $this->assertEquals('comment', $posts[7]->type);
    //     $this->assertEquals(8, $posts[7]->number);

    //     $this->assertEquals("Post 4 in Discussion 2", $posts[8]->content);
    //     $this->assertEquals('comment', $posts[8]->type);
    //     $this->assertEquals(9, $posts[8]->number);

    //     $this->assertEquals("Post 5 in Discussion 2", $posts[9]->content);
    //     $this->assertEquals('comment', $posts[9]->type);
    //     $this->assertEquals(10, $posts[9]->number);

    //     $this->assertEquals("Post 1 in Discussion 3", $posts[10]->content);
    //     $this->assertEquals('comment', $posts[10]->type);
    //     $this->assertEquals(11, $posts[10]->number);

    //     $this->assertEquals("Post 2 in Discussion 3", $posts[11]->content);
    //     $this->assertEquals('comment', $posts[11]->type);
    //     $this->assertEquals(12, $posts[11]->number);

    //     $this->assertEquals("Post 3 in Discussion 3", $posts[12]->content);
    //     $this->assertEquals('comment', $posts[12]->type);
    //     $this->assertEquals(13, $posts[12]->number);

    //     $this->assertEquals("Post 4 in Discussion 3", $posts[13]->content);
    //     $this->assertEquals('comment', $posts[13]->type);
    //     $this->assertEquals(14, $posts[13]->number);

    //     $this->assertEquals("Post 5 in Discussion 3", $posts[14]->content);
    //     $this->assertEquals('comment', $posts[14]->type);
    //     $this->assertEquals(15, $posts[14]->number);

    //     $this->assertEquals('discussionMerged', $posts[15]->type);
    //     $this->assertEquals(16, $posts[15]->number);

    //     // Test the merged discussion has a 301 redirect to the target discussion

    //     $response = $this->send(
    //         $this->request('GET', "/d/2", [])
    //     );

    //     $this->assertEquals(301, $response->getStatusCode());
    //     $this->assertEquals("/d/1", $response->getHeader('Location')[0]);

    //     $response = $this->send(
    //         $this->request('GET', "/d/3", [])
    //     );

    //     $this->assertEquals(301, $response->getStatusCode());
    //     $this->assertEquals("/d/1", $response->getHeader('Location')[0]);
    // }
}
