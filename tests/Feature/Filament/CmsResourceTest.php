<?php

namespace Tests\Feature\Filament;

use App\Enums\ContentNodeStatus;
use App\Filament\Resources\ContentNodeResource\Pages\CreateContentNode;
use App\Filament\Resources\ContentNodeResource\Pages\EditContentNode;
use App\Filament\Resources\ContentNodeResource\Pages\ListContentNodes;
use App\Filament\Resources\ContentNodeResource\RelationManagers\ChildrenRelationManager;
use App\Filament\Resources\ContentNodeResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ContentNodeResource\RelationManagers\LinksRelationManager;
use App\Filament\Resources\ContentNodeResource\RelationManagers\TranslationsRelationManager;
use App\Filament\Resources\ContentTranslationResource\Pages\CreateContentTranslation;
use App\Filament\Resources\DocumentResource\Pages\CreateDocument;
use App\Filament\Resources\LinkResource\Pages\CreateLink;
use App\Models\ContentNode;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CmsResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private ?ContentNode $editionNode = null;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->editor = User::factory()->admin()->create();
        $this->actingAs($this->editor);
    }

    public function test_standalone_resource_forms_expose_the_required_editorial_fields(): void
    {
        Livewire::test(CreateContentNode::class)
            ->assertSuccessful()
            ->assertFormFieldExists('parent_id')
            ->assertFormFieldExists('type')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('position')
            ->assertFormFieldExists('status')
            ->assertFormFieldExists('meta');

        Livewire::test(CreateContentTranslation::class)
            ->assertSuccessful()
            ->assertFormFieldExists('content_node_id')
            ->assertFormFieldExists('locale')
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('body');

        Livewire::test(CreateDocument::class)
            ->assertSuccessful()
            ->assertFormFieldExists('content_node_id')
            ->assertFormFieldExists('path')
            ->assertFormFieldExists('external_url')
            ->assertFormFieldExists('page_start')
            ->assertFormFieldExists('page_end');

        Livewire::test(CreateLink::class)
            ->assertSuccessful()
            ->assertFormFieldExists('content_node_id')
            ->assertFormFieldExists('label')
            ->assertFormFieldExists('url');
    }

    public function test_content_node_relation_managers_create_associated_content(): void
    {
        $node = $this->createNode('assembly');
        $properties = [
            'ownerRecord' => $node,
            'pageClass' => EditContentNode::class,
        ];

        Livewire::test(TranslationsRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'locale' => 'fr',
                'title' => 'Assemblée',
                'body' => '<p>Présentation</p>',
            ])
            ->assertHasNoTableActionErrors();

        Livewire::test(DocumentsRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'title' => 'AU Handbook',
                'kind' => 'pdf',
                'external_url' => 'https://au.int/handbook.pdf',
                'page_start' => 10,
                'page_end' => 12,
            ])
            ->assertHasNoTableActionErrors();

        Livewire::test(LinksRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'label' => 'Official page',
                'url' => 'https://au.int/assembly',
            ])
            ->assertHasNoTableActionErrors();

        Livewire::test(ChildrenRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'type' => 'chapter',
                'slug' => 'assembly-overview',
                'position' => 1,
                'status' => ContentNodeStatus::Draft->value,
                'edition' => '2023',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('content_translations', [
            'content_node_id' => $node->id,
            'locale' => 'fr',
            'title' => 'Assemblée',
        ]);
        $this->assertDatabaseHas('documents', [
            'content_node_id' => $node->id,
            'page_start' => 10,
            'page_end' => 12,
        ]);
        $this->assertDatabaseHas('links', [
            'content_node_id' => $node->id,
            'label' => 'Official page',
        ]);
        $this->assertDatabaseHas('content_nodes', [
            'parent_id' => $node->id,
            'slug' => 'assembly-overview',
            'editor_id' => $this->editor->id,
        ]);
    }

    public function test_content_nodes_and_children_can_be_drag_reordered(): void
    {
        $first = $this->createNode('first', 1);
        $second = $this->createNode('second', 2);
        $third = $this->createNode('third', 3);

        Livewire::test(ListContentNodes::class)
            ->call('reorderTable', [$third->id, $first->id, $second->id])
            ->assertHasNoErrors();

        $this->assertSame(1, $third->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(3, $second->fresh()->position);

        $childOne = $this->createNode('child-one', 1, $first->id);
        $childTwo = $this->createNode('child-two', 2, $first->id);

        Livewire::test(ChildrenRelationManager::class, [
            'ownerRecord' => $first,
            'pageClass' => EditContentNode::class,
        ])->call('reorderTable', [$childTwo->id, $childOne->id]);

        $this->assertSame(1, $childTwo->fresh()->position);
        $this->assertSame(2, $childOne->fresh()->position);
    }

    public function test_relation_forms_enforce_document_sources_ranges_and_unique_languages(): void
    {
        $node = $this->createNode('assembly');
        $node->translations()->create([
            'locale' => 'en',
            'title' => 'Assembly',
        ]);
        $properties = [
            'ownerRecord' => $node,
            'pageClass' => EditContentNode::class,
        ];

        Livewire::test(DocumentsRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'title' => 'Invalid document',
                'kind' => 'pdf',
                'page_start' => 12,
                'page_end' => 10,
            ])
            ->assertHasTableActionErrors(['external_url', 'page_end']);

        Livewire::test(TranslationsRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'locale' => 'en',
                'title' => 'Duplicate English translation',
            ])
            ->assertHasTableActionErrors(['locale']);
    }

    public function test_external_urls_only_allow_http_and_https(): void
    {
        $node = $this->createNode('assembly');
        $properties = [
            'ownerRecord' => $node,
            'pageClass' => EditContentNode::class,
        ];

        Livewire::test(DocumentsRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'title' => 'Unsafe document',
                'kind' => 'link',
                'external_url' => 'file:///etc/passwd',
            ])
            ->assertHasTableActionErrors(['external_url']);

        Livewire::test(LinksRelationManager::class, $properties)
            ->callTableAction('create', null, [
                'label' => 'Unsafe link',
                'url' => 'javascript:alert(1)',
            ])
            ->assertHasTableActionErrors(['url']);
    }

    public function test_document_uploads_reject_unapproved_file_types(): void
    {
        Storage::fake('public');
        $node = $this->createNode('assembly');

        Livewire::test(DocumentsRelationManager::class, [
            'ownerRecord' => $node,
            'pageClass' => EditContentNode::class,
        ])->callTableAction('create', null, [
            'title' => 'Executable',
            'kind' => 'pdf',
            'path' => UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
        ])->assertHasTableActionErrors(['path']);
    }

    private function createNode(string $slug, int $position = 1, ?int $parentId = null): ContentNode
    {
        $isSection = $parentId === null;

        return ContentNode::create([
            'parent_id' => $isSection ? $this->edition()->id : $parentId,
            'type' => $isSection ? 'section' : 'chapter',
            'slug' => $slug,
            'position' => $position,
            'status' => ContentNodeStatus::Draft,
            'edition' => '2023',
            'editor_id' => $this->editor->id,
        ]);
    }

    private function edition(): ContentNode
    {
        return $this->editionNode ??= ContentNode::create([
            'type' => 'edition',
            'slug' => 'test-handbook-2023',
            'position' => 1,
            'status' => ContentNodeStatus::Draft,
            'edition' => '2023',
            'editor_id' => $this->editor->id,
        ]);
    }
}
