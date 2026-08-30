<?php

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\ContentNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookmarkApiTest extends TestCase
{
    use RefreshDatabase;

    private ?ContentNode $editionNode = null;

    public function test_authenticated_user_can_create_a_bookmark_idempotently(): void
    {
        $user = User::factory()->create();
        $node = $this->createNode();
        Sanctum::actingAs($user, ['bookmarks:read', 'bookmarks:write']);

        $this->postJson('/api/v1/bookmarks', ['content_node_id' => $node->id])
            ->assertCreated()
            ->assertJsonPath('message', 'Bookmark created.')
            ->assertJsonPath('data.node.slug', 'assembly');

        $this->postJson('/api/v1/bookmarks', ['content_node_id' => $node->id])
            ->assertOk()
            ->assertJsonPath('message', 'Bookmark already exists.');

        $this->assertDatabaseCount('bookmarks', 1);
    }

    public function test_user_can_list_only_their_own_bookmarks(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $node = $this->createNode();
        $otherNode = $this->createNode('commission', 'Commission');
        Bookmark::create(['user_id' => $user->id, 'content_node_id' => $node->id]);
        Bookmark::create(['user_id' => $otherUser->id, 'content_node_id' => $otherNode->id]);
        Sanctum::actingAs($user, ['bookmarks:read']);

        $this->getJson('/api/v1/bookmarks?locale=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.node.slug', 'assembly')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_user_can_delete_their_bookmark(): void
    {
        $user = User::factory()->create();
        $node = $this->createNode();
        Bookmark::create(['user_id' => $user->id, 'content_node_id' => $node->id]);
        Sanctum::actingAs($user, ['bookmarks:write']);

        $this->deleteJson("/api/v1/bookmarks/{$node->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $user->id,
            'content_node_id' => $node->id,
        ]);
    }

    public function test_bookmark_endpoints_require_authentication_and_valid_nodes(): void
    {
        $this->getJson('/api/v1/bookmarks')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['bookmarks:write']);

        $this->postJson('/api/v1/bookmarks', ['content_node_id' => 99999])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    private function createNode(string $slug = 'assembly', string $title = 'Assembly'): ContentNode
    {
        $node = ContentNode::create([
            'parent_id' => $this->edition()->id,
            'type' => 'section',
            'slug' => $slug,
            'position' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $node->translations()->create([
            'locale' => 'en',
            'title' => $title,
        ]);

        return $node;
    }

    private function edition(): ContentNode
    {
        return $this->editionNode ??= ContentNode::create([
            'type' => 'edition',
            'slug' => 'test-handbook-2023',
            'position' => 1,
            'status' => 'published',
            'published_at' => now(),
            'edition' => '2023',
        ]);
    }
}
