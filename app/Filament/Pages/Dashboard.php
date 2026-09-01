<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContentNodeResource;
use App\Filament\Resources\ContentTranslationResource;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\LinkResource;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Models\Document;
use App\Models\Link;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Dashboard';

    protected ?string $maxContentWidth = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $monthStart = now()->startOfMonth();
        $currentEdition = ContentNode::query()
            ->roots()
            ->with(['translations', 'documents'])
            ->orderByDesc('edition')
            ->orderByDesc('id')
            ->first();
        $latestImport = Document::query()
            ->where(fn ($query) => $query
                ->whereNotNull('imported_at')
                ->orWhereNotNull('checksum'))
            ->with('node.translations')
            ->orderByRaw('COALESCE(imported_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();
        $recentContent = ContentNode::query()
            ->with(['translations', 'editor', 'parent.translations'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return [
            'stats' => [
                $this->stat(
                    'Total Content',
                    ContentNode::query()->count(),
                    ContentNode::query()->where('created_at', '>=', $monthStart)->count(),
                    'heroicon-o-document-text',
                    ContentNodeResource::getUrl('index'),
                ),
                $this->stat(
                    'Translations',
                    ContentTranslation::query()->count(),
                    ContentTranslation::query()->where('created_at', '>=', $monthStart)->count(),
                    'heroicon-o-language',
                    ContentTranslationResource::getUrl('index'),
                ),
                $this->stat(
                    'Documents',
                    Document::query()->count(),
                    Document::query()->where('created_at', '>=', $monthStart)->count(),
                    'heroicon-o-document',
                    DocumentResource::getUrl('index'),
                ),
                $this->stat(
                    'Links',
                    Link::query()->count(),
                    Link::query()->where('created_at', '>=', $monthStart)->count(),
                    'heroicon-o-link',
                    LinkResource::getUrl('index'),
                ),
            ],
            'currentEdition' => $currentEdition,
            'currentEditionTitle' => $currentEdition === null
                ? null
                : ($currentEdition->translations->firstWhere('locale', 'en')?->title
                    ?? $currentEdition->translations->first()?->title
                    ?? $currentEdition->slug),
            'currentEditionUrl' => $currentEdition === null
                ? ContentNodeResource::getUrl('create')
                : ContentNodeResource::getUrl('edit', ['record' => $currentEdition]),
            'editionDocument' => $currentEdition?->documents->sortByDesc('updated_at')->first(),
            'latestImport' => $latestImport,
            'latestImportUrl' => $latestImport === null
                ? DocumentResource::getUrl('create')
                : DocumentResource::getUrl('edit', ['record' => $latestImport]),
            'latestImportSize' => $this->fileSize($latestImport),
            'recentContent' => $recentContent,
            'activities' => $this->activities(),
            'urls' => [
                'content' => ContentNodeResource::getUrl('index'),
                'createContent' => ContentNodeResource::getUrl('create'),
                'translations' => ContentTranslationResource::getUrl('index'),
                'createTranslation' => ContentTranslationResource::getUrl('create'),
                'documents' => DocumentResource::getUrl('index'),
                'createDocument' => DocumentResource::getUrl('create'),
            ],
        ];
    }

    /**
     * @return array{label: string, value: string, delta: int, icon: string, url: string}
     */
    private function stat(string $label, int $value, int $delta, string $icon, string $url): array
    {
        return [
            'label' => $label,
            'value' => number_format($value),
            'delta' => $delta,
            'icon' => $icon,
            'url' => $url,
        ];
    }

    private function fileSize(?Document $document): ?string
    {
        if ($document?->path === null || ! Storage::disk('public')->exists($document->path)) {
            return null;
        }

        $bytes = Storage::disk('public')->size($document->path);

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    /**
     * @return Collection<int, array{label: string, detail: string, at: \Illuminate\Support\Carbon, icon: string}>
     */
    private function activities(): Collection
    {
        $content = ContentNode::query()
            ->with(['translations', 'editor'])
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (ContentNode $node): array => [
                'label' => 'Updated “'.($node->translations->firstWhere('locale', 'en')?->title ?? $node->slug).'”',
                'detail' => $node->editor?->name === null ? 'Content update' : 'by '.$node->editor->name,
                'at' => $node->updated_at,
                'icon' => 'heroicon-o-pencil-square',
            ]);
        $documents = Document::query()
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Document $document): array => [
                'label' => ($document->imported_at === null ? 'Updated ' : 'Imported ').($document->original_filename ?? $document->title ?? 'document'),
                'detail' => $document->imported_at === null ? 'Document update' : 'PDF import completed',
                'at' => $document->imported_at ?? $document->updated_at,
                'icon' => 'heroicon-o-arrow-up-tray',
            ]);
        $translations = ContentTranslation::query()
            ->with('node')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (ContentTranslation $translation): array => [
                'label' => 'Updated '.$translation->locale.' translation',
                'detail' => $translation->title,
                'at' => $translation->updated_at,
                'icon' => 'heroicon-o-language',
            ]);

        return collect([...$content, ...$documents, ...$translations])
            ->sortByDesc('at')
            ->take(5)
            ->values();
    }
}
