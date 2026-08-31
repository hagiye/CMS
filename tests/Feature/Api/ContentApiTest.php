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

    private ?ContentNode $editionNode = null;

    public function test_nodes_can_be_filtered_and_include_translated_children(): void
    {
        $section = $this->createNode('assembly', 'Assembly', position: 2);
        $this->createNode('member-states', 'Member States', position: 1);
        $this->createNode('assembly-overview', 'Overview', 'chapter', 1, $section->id);

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
            ->assertJsonPath('data.locale', 'fr')
            ->assertJsonPath('data.title', 'Commission de l’Union africaine')
            ->assertJsonPath('data.body', 'Présentation');
    }

    public function test_locale_selection_falls_back_to_language_english_then_any_translation(): void
    {
        $node = $this->createNode('commission', 'Commission');
        $node->translations()->create([
            'locale' => 'fr',
            'title' => 'Commission africaine',
        ]);

        $this->getJson('/api/v1/nodes/commission?locale=fr-CA')
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr')
            ->assertJsonPath('data.title', 'Commission africaine');

        $this->getJson('/api/v1/nodes/commission?locale=sw')
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.title', 'Commission');

        $node->translations()->delete();
        $node->translations()->create([
            'locale' => 'pt',
            'title' => 'Comissão Africana',
        ]);

        $this->getJson('/api/v1/nodes/commission?locale=sw')
            ->assertOk()
            ->assertJsonPath('data.locale', 'pt')
            ->assertJsonPath('data.title', 'Comissão Africana');
    }

    public function test_nested_hierarchy_is_returned_in_stable_sibling_order(): void
    {
        $section = $this->createNode('institutions', 'Institutions', position: 1);
        $laterChapter = $this->createNode('later-chapter', 'Later chapter', 'chapter', 2, $section->id);
        $firstChapter = $this->createNode('first-chapter', 'First chapter', 'chapter', 1, $section->id);
        $this->createNode('second-page', 'Second page', 'page', 2, $firstChapter->id);
        $this->createNode('first-article', 'First article', 'article', 1, $firstChapter->id);

        $draft = $this->createNode('draft-page', 'Draft page', 'page', 0, $firstChapter->id);
        $draft->update(['status' => 'draft', 'published_at' => null]);

        $this->getJson('/api/v1/nodes/institutions?include=children')
            ->assertOk()
            ->assertJsonPath('data.children.0.id', $firstChapter->id)
            ->assertJsonPath('data.children.1.id', $laterChapter->id)
            ->assertJsonPath('data.children.0.children.0.slug', 'first-article')
            ->assertJsonPath('data.children.0.children.1.slug', 'second-page')
            ->assertJsonCount(2, 'data.children.0.children');
    }

    public function test_search_returns_matching_nodes_in_a_standard_pagination_envelope(): void
    {
        $this->createNode('assembly', 'Assembly', body: '<p>Heads of State and <strong>Government</strong></p>');
        $this->createNode('commission', 'Commission', body: 'Administrative body');

        $draft = $this->createNode('draft-heads', 'Draft Heads');
        $draft->update(['status' => 'draft', 'published_at' => null]);

        $assembly = ContentNode::where('slug', 'assembly')->firstOrFail();
        $assembly->translations()->create([
            'locale' => 'fr',
            'title' => 'Chefs d\'Etat',
            'body' => 'Organes directeurs',
        ]);

        $this->getJson('/api/v1/search?q=Heads&locale=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'assembly')
            ->assertJsonPath('data.0.match.locale', 'en')
            ->assertJsonPath('data.0.match.field', 'body')
            ->assertJsonPath('data.0.match.excerpt', 'Heads of State and Government')
            ->assertJsonPath('data.0.breadcrumbs.0.slug', 'test-handbook-2023')
            ->assertJsonPath('data.0.breadcrumbs.1.slug', 'assembly')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/v1/search?q=Commission&locale=en')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.match.field', 'title')
            ->assertJsonPath('data.0.match.excerpt', 'Commission')
            ->assertJsonPath('data.0.slug', 'commission');

        $this->getJson('/api/v1/search?q=Chefs&locale=fr')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'assembly')
            ->assertJsonPath('data.0.locale', 'fr')
            ->assertJsonPath('data.0.title', 'Chefs d\'Etat');

        $this->getJson('/api/v1/search?q=Heads&locale=fr')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/search?q=Commission&locale=fr')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.locale', 'en')
            ->assertJsonPath('data.0.slug', 'commission');

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
        if ($type === 'section' && $parentId === null) {
            $parentId = $this->edition()->id;
        }

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

    private function edition(): ContentNode
    {
        if ($this->editionNode) {
            return $this->editionNode;
        }

        $this->editionNode = ContentNode::create([
            'type' => 'edition',
            'slug' => 'test-handbook-2023',
            'position' => 1,
            'status' => 'published',
            'published_at' => now(),
            'edition' => '2023',
        ]);
        $this->editionNode->translations()->create([
            'locale' => 'en',
            'title' => 'Test Handbook 2023',
        ]);

        return $this->editionNode;
    }
}
