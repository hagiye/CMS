<?php

namespace Tests\Feature\Console;

use App\Enums\ContentNodeStatus;
use App\Models\ContentNode;
use App\Models\Document;
use App\Services\HandbookPdfInspector;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class ImportHandbookTest extends TestCase
{
    use RefreshDatabase;

    private string $pdfPath;

    protected function setUp(): void
    {
        parent::setUp();

        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        $this->pdfPath = $directory.'/handbook-import-test.pdf';
        File::put($this->pdfPath, '%PDF-1.4 test source');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        File::delete($this->pdfPath);

        parent::tearDown();
    }

    public function test_preview_reports_failures_without_writing_records_or_files(): void
    {
        $this->fakeInspection();
        $existingNodeCount = ContentNode::query()->count();

        $this->artisan('handbook:import', [
            'pdfPath' => $this->pdfPath,
            '--from' => 5,
            '--to' => 7,
        ])
            ->expectsOutputToContain('IMPORT PREVIEW')
            ->expectsOutputToContain('Pages requiring manual attention')
            ->expectsOutputToContain('Preview complete')
            ->assertSuccessful();

        $this->assertDatabaseCount('content_nodes', $existingNodeCount);
        $this->assertSame(0, ContentNode::query()->whereNotNull('import_key')->count());
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_commit_is_draft_idempotent_and_preserves_editor_corrections_by_default(): void
    {
        $this->fakeInspection();
        $existingNodeCount = ContentNode::query()->count();
        $arguments = [
            'pdfPath' => $this->pdfPath,
            '--from' => 5,
            '--to' => 7,
            '--edition' => '2025',
            '--commit' => true,
            '--force' => true,
        ];

        $this->artisan('handbook:import', $arguments)->assertSuccessful();

        $checksum = hash_file('sha256', $this->pdfPath);
        $document = Document::query()->sole();
        $importedNodes = ContentNode::query()->whereNotNull('import_key')->get();

        $this->assertSame($checksum, $document->checksum);
        $this->assertSame(basename($this->pdfPath), $document->original_filename);
        $this->assertSame(5, $document->page_start);
        $this->assertSame(7, $document->page_end);
        $this->assertNotNull($document->imported_at);
        Storage::disk('public')->assertExists("handbook-documents/imports/2025/{$checksum}.pdf");
        $this->assertCount(3, $importedNodes);
        $this->assertTrue($importedNodes->every(
            fn (ContentNode $node): bool => $node->status === ContentNodeStatus::Draft
                && $node->source_document_id === $document->id,
        ));
        $this->assertDatabaseCount('content_nodes', $existingNodeCount + 5);

        $page = $importedNodes->firstWhere('type', 'page');
        $translation = $page->translations()->where('locale', 'en')->firstOrFail();
        $translation->update(['body' => '<p>Editor correction</p>']);

        $this->artisan('handbook:import', $arguments)->assertSuccessful();

        $this->assertDatabaseCount('content_nodes', $existingNodeCount + 5);
        $this->assertDatabaseCount('documents', 1);
        $this->assertSame('<p>Editor correction</p>', $translation->fresh()->body);

        $this->artisan('handbook:import', $arguments + ['--refresh' => true])->assertSuccessful();

        $this->assertSame('<p>The Assembly provides strategic direction.</p>', $translation->fresh()->body);
        $this->assertDatabaseCount('content_nodes', $existingNodeCount + 5);
    }

    public function test_source_url_rejects_non_http_schemes(): void
    {
        $this->artisan('handbook:import', [
            'pdfPath' => $this->pdfPath,
            '--source-url' => 'file:///etc/passwd',
        ])
            ->expectsOutput('--source-url must be a valid HTTP or HTTPS URL.')
            ->assertFailed();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_parser_title_changes_reuse_the_same_page_nodes(): void
    {
        $this->mock(HandbookPdfInspector::class, function (MockInterface $mock): void {
            $mock->shouldReceive('inspect')->andReturn(
                $this->inspection(),
                $this->inspection('Assembly and Heads of State', 'Updated extracted text.'),
            );
        });
        $arguments = [
            'pdfPath' => $this->pdfPath,
            '--from' => 5,
            '--to' => 7,
            '--edition' => '2025',
            '--commit' => true,
            '--force' => true,
        ];

        $this->artisan('handbook:import', $arguments)->assertSuccessful();

        $page = ContentNode::query()
            ->where('type', 'page')
            ->where('source_page_start', 5)
            ->sole();
        $pageCount = ContentNode::query()->where('type', 'page')->count();

        $this->artisan('handbook:import', $arguments + ['--refresh' => true])->assertSuccessful();

        $this->assertSame($pageCount, ContentNode::query()->where('type', 'page')->count());
        $this->assertSame($page->id, ContentNode::query()
            ->where('type', 'page')
            ->where('source_page_start', 5)
            ->sole()
            ->id);
        $this->assertDatabaseHas('content_translations', [
            'content_node_id' => $page->id,
            'locale' => 'en',
            'title' => 'Assembly and Heads of State',
            'body' => '<p>Updated extracted text.</p>',
        ]);
    }

    public function test_document_checksum_is_unique_at_the_database_boundary(): void
    {
        Document::create([
            'kind' => 'pdf',
            'title' => 'First copy',
            'checksum' => str_repeat('a', 64),
        ]);

        $this->expectException(QueryException::class);

        Document::create([
            'kind' => 'pdf',
            'title' => 'Second copy',
            'checksum' => str_repeat('a', 64),
        ]);
    }

    private function fakeInspection(
        string $firstTitle = 'Assembly',
        string $firstBody = 'The Assembly provides strategic direction.',
    ): void
    {
        $inspection = $this->inspection($firstTitle, $firstBody);

        $this->mock(HandbookPdfInspector::class, function (MockInterface $mock) use ($inspection): void {
            $mock->shouldReceive('inspect')->andReturn($inspection);
        });
    }

    /**
     * @return array{total_pages: int, from: int, to: int, pages: array, failures: array, segments: array}
     */
    private function inspection(
        string $firstTitle = 'Assembly',
        string $firstBody = 'The Assembly provides strategic direction.',
    ): array {
        return [
            'total_pages' => 7,
            'from' => 5,
            'to' => 7,
            'pages' => [
                ['number' => 5, 'text' => 'Assembly'],
                ['number' => 6, 'text' => 'Executive Council'],
            ],
            'failures' => [7 => 'No extractable text was found.'],
            'segments' => [
                [
                    'title' => $firstTitle,
                    'body' => $firstBody,
                    'page_start' => 5,
                    'page_end' => 5,
                ],
                [
                    'title' => 'Executive Council',
                    'body' => 'The Executive Council coordinates policy.',
                    'page_start' => 6,
                    'page_end' => 6,
                ],
            ],
        ];
    }
}
