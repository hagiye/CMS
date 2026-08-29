<?php

namespace Tests\Feature\Console;

use App\Enums\ContentNodeStatus;
use App\Models\ContentNode;
use App\Models\Document;
use App\Services\HandbookPdfInspector;
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

    private function fakeInspection(): void
    {
        $inspection = [
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
                    'title' => 'Assembly',
                    'body' => 'The Assembly provides strategic direction.',
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

        $this->mock(HandbookPdfInspector::class, function (MockInterface $mock) use ($inspection): void {
            $mock->shouldReceive('inspect')->andReturn($inspection);
        });
    }
}
