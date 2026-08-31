<?php

namespace App\Console\Commands;

use App\Enums\ContentNodeStatus;
use App\Enums\ContentNodeType;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Models\Document;
use App\Services\HandbookPdfInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportHandbook extends Command
{
    protected $signature = 'handbook:import
                            {pdfPath : Absolute or relative path to the PDF}
                            {--lang=en : Locale for extracted translations}
                            {--edition=2023 : Handbook edition}
                            {--from=1 : Start page (1-based, inclusive)}
                            {--to=99999 : End page (1-based, inclusive)}
                            {--section=African Union Commission : Parent section title}
                            {--section-slug= : Existing or proposed section slug}
                            {--chapter= : Chapter title for this imported range}
                            {--source-url= : Optional canonical source URL}
                            {--commit : Persist the previewed import}
                            {--force : Commit without an interactive confirmation}
                            {--refresh : Replace extracted translations on existing draft/review nodes}';

    protected $description = 'Preview and optionally import a handbook PDF as reviewable draft content';

    public function __construct(private readonly HandbookPdfInspector $inspector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = realpath((string) $this->argument('pdfPath'));
        $lang = trim((string) $this->option('lang'));
        $edition = trim((string) $this->option('edition'));
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');
        $sectionTitle = trim((string) $this->option('section'));
        $sourceUrl = trim((string) $this->option('source-url')) ?: null;

        if ($path === false || ! is_file($path)) {
            $this->error('PDF not found: '.(string) $this->argument('pdfPath'));

            return self::FAILURE;
        }

        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            $this->error('The import source must be a PDF file.');

            return self::FAILURE;
        }

        if ($from < 1 || $to < $from) {
            $this->error('--from must be at least 1 and --to must be greater than or equal to --from.');

            return self::FAILURE;
        }

        if (! preg_match('/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/', $lang)) {
            $this->error('--lang must be a two-letter locale, optionally followed by a region.');

            return self::FAILURE;
        }

        if ($edition === '' || mb_strlen($edition) > 20 || $sectionTitle === '') {
            $this->error('--edition and --section are required; edition may not exceed 20 characters.');

            return self::FAILURE;
        }

        if ($sourceUrl !== null && ! $this->isHttpUrl($sourceUrl)) {
            $this->error('--source-url must be a valid HTTP or HTTPS URL.');

            return self::FAILURE;
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $this->info("Inspecting {$path} (requested pages {$from}-{$to})...");

        try {
            $inspection = $this->inspector->inspect($path, $from, $to);
            $checksum = hash_file('sha256', $path);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($checksum === false) {
            $this->error('The PDF checksum could not be calculated.');

            return self::FAILURE;
        }

        if ($inspection['to'] < $to) {
            $this->warn("The PDF has {$inspection['total_pages']} pages; the import range ends at page {$inspection['to']}.");
        }

        $sectionSlug = Str::slug(trim((string) $this->option('section-slug')));
        $sectionSlug = $sectionSlug !== ''
            ? $sectionSlug
            : Str::slug($sectionTitle).'-'.Str::slug($edition);
        $chapterTitle = trim((string) $this->option('chapter'));
        $chapterTitle = $chapterTitle !== ''
            ? $chapterTitle
            : "Imported pages {$inspection['from']}-{$inspection['to']}";
        $segments = $this->prepareSegments(
            $inspection['segments'],
            $checksum,
            $lang,
            $sectionSlug,
        );

        $this->renderFailures($inspection['failures']);

        if ($segments === []) {
            $this->error('No meaningful content segments could be extracted. Nothing can be imported.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('IMPORT PREVIEW — no records have been changed');
        $this->table(
            ['#', 'Proposed title', 'Pages', 'Characters', 'Proposed slug'],
            array_map(
                fn (array $segment, int $index): array => [
                    $index + 1,
                    $segment['title'],
                    $segment['page_start'] === $segment['page_end']
                        ? (string) $segment['page_start']
                        : $segment['page_start'].'-'.$segment['page_end'],
                    mb_strlen($segment['body']),
                    $segment['slug'],
                ],
                $segments,
                array_keys($segments),
            ),
        );
        $this->line(sprintf(
            '%d segments from %d parsed pages; %d pages could not be parsed. All new content will be draft.',
            count($segments),
            count($inspection['pages']),
            count($inspection['failures']),
        ));

        if (! $this->option('commit')) {
            $this->newLine();
            $this->info('Preview complete. Run again with --commit after reviewing the proposed sections.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Commit this draft import?', false)) {
            $this->warn('Import cancelled; no records were changed.');

            return self::SUCCESS;
        }

        try {
            $storedPath = $this->storeSourcePdf($path, $edition, $checksum);
            $result = DB::transaction(fn (): array => $this->persistImport(
                $path,
                $storedPath,
                $sourceUrl,
                $checksum,
                $edition,
                $lang,
                $sectionTitle,
                $sectionSlug,
                $chapterTitle,
                $inspection,
                $segments,
                (bool) $this->option('refresh'),
            ));
        } catch (Throwable $exception) {
            $this->error('Import failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Draft import complete: %d nodes created, %d reused, %d translations refreshed.',
            $result['created'],
            $result['reused'],
            $result['refreshed'],
        ));
        $this->line('The extracted text is ready for review and correction in Filament. Nothing was published.');

        return self::SUCCESS;
    }

    private function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * @param  array<int, array{title: string, body: string, page_start: int, page_end: int}>  $segments
     * @return array<int, array{title: string, body: string, page_start: int, page_end: int, import_key: string, legacy_import_key: string, slug: string, position: int}>
     */
    private function prepareSegments(array $segments, string $checksum, string $lang, string $sectionSlug): array
    {
        $rangeOccurrences = [];
        $legacyOccurrences = [];

        foreach ($segments as $index => &$segment) {
            $rangeIdentity = $segment['page_start'].'|'.$segment['page_end'];
            $rangeOccurrences[$rangeIdentity] = ($rangeOccurrences[$rangeIdentity] ?? 0) + 1;
            $occurrence = $rangeOccurrences[$rangeIdentity];
            $segment['import_key'] = hash(
                'sha256',
                implode('|', [$checksum, 'segment-v2', $sectionSlug, $lang, $rangeIdentity, $occurrence]),
            );

            // Retain the original title-based identity so imports made before the
            // stable range identity was introduced are reused on their next run.
            $legacyIdentity = mb_strtolower($segment['title']).'|'.$rangeIdentity;
            $legacyOccurrences[$legacyIdentity] = ($legacyOccurrences[$legacyIdentity] ?? 0) + 1;
            $segment['legacy_import_key'] = hash(
                'sha256',
                implode('|', [
                    $checksum,
                    'segment',
                    $sectionSlug,
                    $lang,
                    $legacyIdentity,
                    $legacyOccurrences[$legacyIdentity],
                ]),
            );
            $slugTitle = Str::slug($segment['title']) ?: 'content';
            $segment['slug'] = Str::limit(
                $sectionSlug.'-p'.$segment['page_start'].'-'.$slugTitle,
                220,
                '',
            );
            if ($occurrence > 1) {
                $segment['slug'] .= '-'.$occurrence;
            }
            $segment['position'] = $index + 1;
        }
        unset($segment);

        return $segments;
    }

    /**
     * @param  array<int, string>  $failures
     */
    private function renderFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        $this->newLine();
        $this->warn('Pages requiring manual attention:');
        $this->table(
            ['Page', 'Reason'],
            array_map(
                fn (int $page, string $reason): array => [$page, $reason],
                array_keys($failures),
                array_values($failures),
            ),
        );
    }

    private function storeSourcePdf(string $sourcePath, string $edition, string $checksum): string
    {
        $storedPath = 'handbook-documents/imports/'.Str::slug($edition).'/'.$checksum.'.pdf';
        $disk = Storage::disk('public');

        if ($disk->exists($storedPath)) {
            return $storedPath;
        }

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The source PDF could not be opened for storage.');
        }

        try {
            if (! $disk->put($storedPath, $stream)) {
                throw new RuntimeException('The source PDF could not be copied to public storage.');
            }
        } finally {
            fclose($stream);
        }

        return $storedPath;
    }

    /**
     * @param  array{total_pages: int, from: int, to: int, pages: array<int, array{number: int, text: string}>, failures: array<int, string>, segments: array}  $inspection
     * @param  array<int, array{title: string, body: string, page_start: int, page_end: int, import_key: string, legacy_import_key: string, slug: string, position: int}>  $segments
     * @return array{created: int, reused: int, refreshed: int}
     */
    private function persistImport(
        string $sourcePath,
        string $storedPath,
        ?string $sourceUrl,
        string $checksum,
        string $edition,
        string $lang,
        string $sectionTitle,
        string $sectionSlug,
        string $chapterTitle,
        array $inspection,
        array $segments,
        bool $refresh,
    ): array {
        $counts = ['created' => 0, 'reused' => 0, 'refreshed' => 0];
        $editionNode = ContentNode::query()
            ->where('type', ContentNodeType::Edition->value)
            ->where('edition', $edition)
            ->first();

        if ($editionNode === null) {
            $editionNode = ContentNode::create([
                'type' => ContentNodeType::Edition->value,
                'slug' => $this->availableSlug('au-handbook-'.Str::slug($edition)),
                'position' => 1,
                'status' => ContentNodeStatus::Draft,
                'edition' => $edition,
            ]);
            $counts['created']++;
        } else {
            $counts['reused']++;
        }

        $counts['refreshed'] += $this->writeTranslation(
            $editionNode,
            $lang,
            "African Union Handbook {$edition}",
            null,
            $refresh,
        );

        $document = Document::query()->firstOrCreate(
            ['checksum' => $checksum],
            [
                'content_node_id' => $editionNode->id,
                'kind' => 'pdf',
                'title' => "African Union Handbook {$edition} (".strtoupper($lang).')',
                'path' => $storedPath,
                'external_url' => $sourceUrl,
                'page_start' => $inspection['from'],
                'page_end' => $inspection['to'],
                'checksum' => $checksum,
                'original_filename' => basename($sourcePath),
                'imported_at' => now(),
            ],
        );

        if (! $document->wasRecentlyCreated) {
            $document->update([
                'path' => $storedPath,
                'external_url' => $sourceUrl ?? $document->external_url,
                'page_start' => min($document->page_start ?? $inspection['from'], $inspection['from']),
                'page_end' => max($document->page_end ?? $inspection['to'], $inspection['to']),
            ]);
        }

        $section = ContentNode::query()->firstOrCreate(
            ['slug' => $sectionSlug],
            [
                'parent_id' => $editionNode->id,
                'type' => ContentNodeType::Section->value,
                'position' => ((int) $editionNode->children()->max('position')) + 1,
                'status' => ContentNodeStatus::Draft,
                'edition' => $edition,
                'source_page_start' => $inspection['from'],
                'source_page_end' => $inspection['to'],
                'source_document_id' => $document->id,
            ],
        );

        if ($section->wasRecentlyCreated) {
            $counts['created']++;
        } else {
            $this->assertNode($section, ContentNodeType::Section, $editionNode->id, '--section-slug');
            $counts['reused']++;
        }

        $counts['refreshed'] += $this->writeTranslation($section, $lang, $sectionTitle, null, $refresh);

        $chapterKey = hash(
            'sha256',
            implode('|', [$checksum, 'chapter', $sectionSlug, $lang, $inspection['from'], $inspection['to']]),
        );
        $chapter = ContentNode::query()->firstOrCreate(
            ['import_key' => $chapterKey],
            [
                'parent_id' => $section->id,
                'type' => ContentNodeType::Chapter->value,
                'slug' => $this->availableSlug(
                    $sectionSlug.'-import-'.$inspection['from'].'-'.$inspection['to'],
                    $chapterKey,
                ),
                'position' => ((int) $section->children()->max('position')) + 1,
                'status' => ContentNodeStatus::Draft,
                'edition' => $edition,
                'source_page_start' => $inspection['from'],
                'source_page_end' => $inspection['to'],
                'source_document_id' => $document->id,
            ],
        );

        if ($chapter->wasRecentlyCreated) {
            $counts['created']++;
        } else {
            $this->assertNode($chapter, ContentNodeType::Chapter, $section->id, 'import chapter');
            $counts['reused']++;
        }

        $counts['refreshed'] += $this->writeTranslation($chapter, $lang, $chapterTitle, null, $refresh);

        foreach ($segments as $segment) {
            $node = ContentNode::query()->where('import_key', $segment['import_key'])->first();

            if ($node === null) {
                $node = ContentNode::query()
                    ->where('import_key', $segment['legacy_import_key'])
                    ->first();

                if ($node !== null) {
                    $this->assertNode($node, ContentNodeType::Page, $chapter->id, 'import segment');
                    $node->update(['import_key' => $segment['import_key']]);
                }
            }

            if ($node === null) {
                $node = ContentNode::query()->firstOrCreate(
                    ['import_key' => $segment['import_key']],
                    [
                    'parent_id' => $chapter->id,
                    'type' => ContentNodeType::Page->value,
                    'slug' => $this->availableSlug($segment['slug'], $segment['import_key']),
                    'position' => $segment['position'],
                    'status' => ContentNodeStatus::Draft,
                    'edition' => $edition,
                    'source_page_start' => $segment['page_start'],
                    'source_page_end' => $segment['page_end'],
                    'source_document_id' => $document->id,
                    ],
                );
            }

            if ($node->wasRecentlyCreated) {
                $counts['created']++;
            } else {
                $this->assertNode($node, ContentNodeType::Page, $chapter->id, 'import segment');
                $counts['reused']++;
            }

            $counts['refreshed'] += $this->writeTranslation(
                $node,
                $lang,
                $segment['title'],
                $this->richText($segment['body']),
                $refresh,
            );
        }

        return $counts;
    }

    private function assertNode(ContentNode $node, ContentNodeType $type, int $parentId, string $source): void
    {
        if ($node->nodeType() !== $type || (int) $node->parent_id !== $parentId) {
            throw new RuntimeException("The existing {$source} points to an incompatible content node.");
        }
    }

    private function availableSlug(string $proposed, ?string $importKey = null): string
    {
        $proposed = Str::limit(Str::slug($proposed), 220, '');
        $existing = ContentNode::query()->where('slug', $proposed)->first();

        if ($existing === null || ($importKey !== null && $existing->import_key === $importKey)) {
            return $proposed;
        }

        return $proposed.'-'.substr($importKey ?? hash('sha256', $proposed.microtime()), 0, 8);
    }

    private function writeTranslation(
        ContentNode $node,
        string $locale,
        string $title,
        ?string $body,
        bool $refresh,
    ): int {
        $translation = ContentTranslation::query()->firstOrCreate(
            ['content_node_id' => $node->id, 'locale' => $locale],
            ['title' => $title, 'body' => $body],
        );

        if (! $translation->wasRecentlyCreated
            && $refresh
            && in_array($node->status, [ContentNodeStatus::Draft, ContentNodeStatus::Review], true)) {
            $translation->update(['title' => $title, 'body' => $body]);

            return 1;
        }

        return 0;
    }

    private function richText(string $text): string
    {
        $paragraphs = preg_split('/\n{2,}/u', trim($text)) ?: [];

        return implode('', array_map(
            fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>',
            $paragraphs,
        ));
    }
}
