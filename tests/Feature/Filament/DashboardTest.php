<?php

namespace Tests\Feature\Filament;

use App\Enums\ContentNodeStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ContentNodeResource;
use App\Filament\Resources\ContentTranslationResource;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\LinkResource;
use App\Models\ContentNode;
use App\Models\Document;
use App\Models\Link;
use App\Models\User;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create(['name' => 'Admin User']));
    }

    public function test_dashboard_renders_live_handbook_metrics_and_recent_records(): void
    {
        $edition = ContentNode::create([
            'type' => 'edition',
            'slug' => 'au-handbook-2027',
            'position' => 1,
            'status' => ContentNodeStatus::Published,
            'published_at' => now(),
            'edition' => '2027',
        ]);
        $edition->translations()->create([
            'locale' => 'en',
            'title' => 'African Union Handbook 2027',
        ]);
        Document::create([
            'content_node_id' => $edition->id,
            'kind' => 'pdf',
            'title' => '2027 Handbook PDF',
            'original_filename' => 'AU_Handbook_2027.pdf',
            'checksum' => str_repeat('a', 64),
            'page_start' => 1,
            'page_end' => 640,
            'imported_at' => now(),
        ]);
        Link::create([
            'content_node_id' => $edition->id,
            'label' => 'Official handbook',
            'url' => 'https://au.int/handbook',
        ]);

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('African Union Handbook 2027')
            ->assertSee('AU_Handbook_2027.pdf')
            ->assertSee('Total Content')
            ->assertSee('Translations')
            ->assertSee('Documents')
            ->assertSee('Links')
            ->assertSee('Recent Activity');
    }

    public function test_editorial_resources_are_available_to_global_search(): void
    {
        $edition = ContentNode::create([
            'type' => 'edition',
            'slug' => 'searchable-handbook',
            'position' => 1,
            'status' => ContentNodeStatus::Draft,
            'edition' => '2027',
        ]);
        $edition->translations()->create([
            'locale' => 'en',
            'title' => 'Searchable Assembly Handbook',
        ]);
        Document::create([
            'content_node_id' => $edition->id,
            'kind' => 'pdf',
            'title' => 'Searchable Handbook PDF',
            'external_url' => 'https://au.int/searchable-handbook.pdf',
        ]);
        Link::create([
            'content_node_id' => $edition->id,
            'label' => 'Searchable official source',
            'url' => 'https://au.int/searchable-handbook',
        ]);

        $this->assertSame(['slug', 'translations.title'], ContentNodeResource::getGloballySearchableAttributes());
        $this->assertCount(1, ContentNodeResource::getGlobalSearchResults('Assembly'));
        $this->assertCount(1, ContentTranslationResource::getGlobalSearchResults('Assembly'));
        $this->assertCount(1, DocumentResource::getGlobalSearchResults('Searchable Handbook'));
        $this->assertCount(1, LinkResource::getGlobalSearchResults('official source'));
    }

    public function test_panel_supports_light_and_dark_modes_with_dark_as_the_default(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasDarkMode());
        $this->assertFalse($panel->hasDarkModeForced());
        $this->assertSame(ThemeMode::Dark, $panel->getDefaultThemeMode());
    }
}
