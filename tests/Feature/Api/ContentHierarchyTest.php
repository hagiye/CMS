<?php

namespace Tests\Feature\Api;

use App\Enums\ContentNodeStatus;
use App\Models\ContentNode;
use Database\Seeders\HandbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_controlled_hierarchy_is_supported_and_exposed_by_the_api(): void
    {
        $edition = ContentNode::where('slug', 'au-handbook-2023')->firstOrFail();
        $section = $this->createNode($edition, 'section', 'institutions');
        $chapter = $this->createNode($section, 'chapter', 'assembly');
        $article = $this->createNode($chapter, 'article', 'assembly-composition');
        $page = $this->createNode($chapter, 'page', 'assembly-contacts');

        $this->assertTrue($section->parent->is($edition));
        $this->assertTrue($chapter->parent->is($section));
        $this->assertTrue($article->parent->is($chapter));
        $this->assertTrue($page->parent->is($chapter));
        $this->assertSame('2023', $article->edition);

        $this->getJson('/api/v1/nodes?type=edition&include=children')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'au-handbook-2023')
            ->assertJsonPath('data.0.children.0.slug', 'institutions')
            ->assertJsonPath('data.0.children.0.parent_id', $edition->id);
    }

    public function test_invalid_parent_type_combinations_are_rejected(): void
    {
        $edition = ContentNode::where('slug', 'au-handbook-2023')->firstOrFail();
        $section = $this->createNode($edition, 'section', 'institutions');

        $invalidOperations = [
            fn () => ContentNode::create([
                'type' => 'section',
                'slug' => 'orphan-section',
                'edition' => '2023',
            ]),
            fn () => ContentNode::create([
                'parent_id' => $section->id,
                'type' => 'article',
                'slug' => 'article-without-chapter',
            ]),
            fn () => ContentNode::create([
                'parent_id' => $edition->id,
                'type' => 'edition',
                'slug' => 'nested-edition',
                'edition' => '2024',
            ]),
        ];

        foreach ($invalidOperations as $operation) {
            try {
                $operation();
                $this->fail('Invalid hierarchy operation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('parent_id', $exception->errors());
            }
        }
    }

    public function test_edition_changes_propagate_to_all_descendants(): void
    {
        $edition2023 = ContentNode::where('slug', 'au-handbook-2023')->firstOrFail();
        $edition2024 = ContentNode::create([
            'type' => 'edition',
            'slug' => 'au-handbook-2024',
            'edition' => '2024',
            'status' => ContentNodeStatus::Draft,
        ]);
        $section = $this->createNode($edition2023, 'section', 'institutions');
        $chapter = $this->createNode($section, 'chapter', 'assembly');
        $article = $this->createNode($chapter, 'article', 'assembly-composition');

        $section->update(['parent_id' => $edition2024->id]);

        $this->assertSame('2024', $section->fresh()->edition);
        $this->assertSame('2024', $chapter->fresh()->edition);
        $this->assertSame('2024', $article->fresh()->edition);
    }

    public function test_seeded_text_lives_in_translations_and_structural_metadata_uses_columns(): void
    {
        $this->seed(HandbookSeeder::class);

        $commission = ContentNode::where('slug', 'african-union-commission-2023')->firstOrFail();

        $this->assertFalse(Schema::hasColumn('content_nodes', 'title'));
        $this->assertFalse(Schema::hasColumn('content_nodes', 'body'));
        $this->assertSame(92, $commission->source_page_start);
        $this->assertNull($commission->meta);
        $this->assertDatabaseHas('content_translations', [
            'content_node_id' => $commission->id,
            'locale' => 'en',
            'title' => 'African Union Commission',
        ]);
    }

    private function createNode(ContentNode $parent, string $type, string $slug): ContentNode
    {
        $node = ContentNode::create([
            'parent_id' => $parent->id,
            'type' => $type,
            'slug' => $slug,
            'position' => 1,
            'status' => ContentNodeStatus::Published,
            'published_at' => now(),
        ]);
        $node->translations()->create([
            'locale' => 'en',
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
        ]);

        return $node;
    }
}
