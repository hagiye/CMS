<?php

namespace Tests\Feature\Api;

use App\Enums\ContentNodeStatus;
use App\Filament\Resources\ContentNodeResource as FilamentContentNodeResource;
use App\Models\Bookmark;
use App\Models\ContentNode;
use App\Models\Document;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ?ContentNode $editionNode = null;

    public function test_only_currently_published_nodes_and_children_are_public(): void
    {
        $parent = $this->createNode('published-section', ContentNodeStatus::Published, now(), [
            'edition' => '2023',
            'source_page_start' => 10,
            'source_page_end' => 12,
            'revision' => 3,
        ]);
        $this->createNode('published-child', ContentNodeStatus::Published, now(), [
            'parent_id' => $parent->id,
            'type' => 'chapter',
        ]);
        $this->createNode('draft-child', ContentNodeStatus::Draft, null, [
            'parent_id' => $parent->id,
            'type' => 'chapter',
        ]);
        $this->createNode('draft-section', ContentNodeStatus::Draft);
        $this->createNode('review-section', ContentNodeStatus::Review);
        $this->createNode('archived-section', ContentNodeStatus::Archived, now()->subDay());
        $this->createNode('scheduled-section', ContentNodeStatus::Published, now()->addDay());

        $this->getJson('/api/v1/nodes?type=section&include=children')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'published-section')
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.edition', '2023')
            ->assertJsonPath('data.0.source_page_start', 10)
            ->assertJsonPath('data.0.source_page_end', 12)
            ->assertJsonPath('data.0.revision', 3)
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.slug', 'published-child');
    }

    public function test_non_published_content_is_hidden_from_detail_search_and_attachment_endpoints(): void
    {
        $draft = $this->createNode('private-draft', ContentNodeStatus::Draft);
        Document::create([
            'content_node_id' => $draft->id,
            'kind' => 'pdf',
            'title' => 'Private draft',
        ]);
        Link::create([
            'content_node_id' => $draft->id,
            'label' => 'Private source',
            'url' => 'https://example.com/private',
        ]);

        $this->getJson('/api/v1/nodes/private-draft')->assertNotFound();
        $this->getJson('/api/v1/nodes/private-draft/documents')->assertNotFound();
        $this->getJson('/api/v1/nodes/private-draft/links')->assertNotFound();
        $this->getJson('/api/v1/search?q=Private')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_bookmark_api_does_not_expose_non_published_nodes(): void
    {
        $user = User::factory()->create();
        $draft = $this->createNode('private-draft', ContentNodeStatus::Draft);
        Bookmark::create([
            'user_id' => $user->id,
            'content_node_id' => $draft->id,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookmarks', ['content_node_id' => $draft->id])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);

        $this->getJson('/api/v1/bookmarks')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->deleteJson("/api/v1/bookmarks/{$draft->id}")
            ->assertNotFound();
    }

    public function test_new_nodes_default_to_draft_and_publishing_sets_the_timestamp(): void
    {
        $editor = User::factory()->create();
        $draft = ContentNode::create([
            'parent_id' => $this->edition()->id,
            'type' => 'section',
            'slug' => 'new-draft',
            'position' => 1,
            'editor_id' => $editor->id,
        ])->refresh();

        $this->assertSame(ContentNodeStatus::Draft, $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertSame(1, $draft->revision);
        $this->assertTrue($draft->editor->is($editor));
        $this->assertTrue(FilamentContentNodeResource::getEloquentQuery()->whereKey($draft)->exists());

        $draft->update(['status' => ContentNodeStatus::Published]);

        $this->assertNotNull($draft->fresh()->published_at);
        $this->assertTrue($draft->fresh()->isPublished());
    }

    private function createNode(
        string $slug,
        ContentNodeStatus $status,
        $publishedAt = null,
        array $attributes = [],
    ): ContentNode {
        if (($attributes['type'] ?? 'section') === 'section' && ! isset($attributes['parent_id'])) {
            $attributes['parent_id'] = $this->edition()->id;
        }

        $node = ContentNode::create(array_merge([
            'type' => 'section',
            'slug' => $slug,
            'position' => 1,
            'status' => $status,
            'published_at' => $publishedAt,
        ], $attributes));

        $node->translations()->create([
            'locale' => 'en',
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'body' => "Body for {$slug}",
        ]);

        return $node;
    }

    private function edition(): ContentNode
    {
        return $this->editionNode ??= ContentNode::create([
            'type' => 'edition',
            'slug' => 'test-handbook-2023',
            'position' => 1,
            'status' => ContentNodeStatus::Published,
            'published_at' => now(),
            'edition' => '2023',
        ]);
    }
}
