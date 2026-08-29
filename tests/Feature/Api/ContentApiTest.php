<?php

namespace Tests\Feature\Api;

use App\Models\ContentNode;
use App\Models\Document;
use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nodes_can_be_filtered_and_include_translated_children(): void
    {
        $section = $this->createNode('assembly', 'Assembly', position: 2);
        $this->createNode('member-states', 'Member States', position: 1);
        $this->createNode('assembly-overview', 'Overview', 'article', 1, $section->id);

        $response = $this->getJson('/api/v1/nodes?type=section&locale=en&include=children&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'member-states')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $assembly = $this->getJson('/api/v1/nodes/assembly?locale=en');
        $assembly
            ->assertOk()
            ->assertJsonPath('data.title', 'Assembly')
            ->assertJsonPath('data.children.0.title', 'Overview');
    }

    public function test_node_endpoint_uses_the_requested_locale(): void
    {
        $node = $this->createNode('commission', 'Commission');
        $node->translations()->create([
            'locale' => 'fr',
            'title' => 'Commission de l’Union africaine',
            'body' => 'Présentation',
        ]);

        $this->getJson('/api/v1/nodes/commission?locale=fr')
            ->assertOk()
            ->assertJsonPath('data.title', 'Commission de l’Union africaine')
            ->assertJsonPath('data.body', 'Présentation');
    }

    public function test_search_returns_matching_nodes_in_a_standard_pagination_envelope(): void
    {
        $this->createNode('assembly', 'Assembly', body: 'Heads of State and Government');
        $this->createNode('commission', 'Commission', body: 'Administrative body');

        $this->getJson('/api/v1/search?q=Heads&locale=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'assembly')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/v1/search?q=missing&locale=en')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_links_endpoint_serializes_links_for_a_node(): void
    {
        $node = $this->createNode('assembly', 'Assembly');
        Link::create([
            'content_node_id' => $node->id,
            'label' => 'Official page',
            'url' => 'https://au.int/assembly',
            'meta' => ['source' => 'AU'],
        ]);

        $this->getJson('/api/v1/nodes/assembly/links')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Official page')
            ->assertJsonPath('data.0.meta.source', 'AU');
    }

    public function test_documents_endpoint_serializes_documents_for_a_node(): void
    {
        $node = $this->createNode('assembly', 'Assembly');
        Document::create([
            'content_node_id' => $node->id,
            'kind' => 'pdf',
            'title' => 'AU Handbook',
            'external_url' => 'https://au.int/handbook.pdf',
            'page_start' => 10,
            'page_end' => 12,
            'meta' => ['page_start' => 10],
        ]);

        $this->getJson('/api/v1/nodes/assembly/documents')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'pdf')
            ->assertJsonPath('data.0.external_url', 'https://au.int/handbook.pdf')
            ->assertJsonPath('data.0.page_start', 10)
            ->assertJsonPath('data.0.page_end', 12)
            ->assertJsonPath('data.0.meta.page_start', 10);
    }

    public function test_query_validation_and_not_found_errors_use_the_api_error_shape(): void
    {
        $this->getJson('/api/v1/search?q=x&locale=english')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors(['q', 'locale']);

        $this->getJson('/api/v1/nodes/not-a-node')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);

        $this->getJson('/api/v1/not-a-route')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    private function createNode(
        string $slug,
        string $title,
        string $type = 'section',
        int $position = 1,
        ?int $parentId = null,
        ?string $body = null,
    ): ContentNode {
        $node = ContentNode::create([
            'parent_id' => $parentId,
            'type' => $type,
            'slug' => $slug,
            'position' => $position,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $node->translations()->create([
            'locale' => 'en',
            'title' => $title,
            'body' => $body,
        ]);

        return $node;
    }
}
