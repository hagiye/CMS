<?php

namespace App\Console\Commands;

use App\Enums\ContentNodeStatus;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class ImportHandbook extends Command
{
    /**
     * Usage:
     * php artisan handbook:import storage/app/Handbook_2023_EN.pdf --from=92 --to=110 --lang=en
     */
    protected $signature = 'handbook:import 
                            {pdfPath : Absolute or relative path to the PDF} 
                            {--lang=en : Locale for the translation (e.g., en, fr)} 
                            {--from=92 : Start page (1-based, inclusive)} 
                            {--to=110 : End page (1-based, inclusive)}';

    protected $description = 'Import curated text and a canonical PDF reference for the AU Handbook';

    public function handle(): int
    {
        $pdfPath = (string) $this->argument('pdfPath');
        $lang = (string) $this->option('lang');
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        if (! file_exists($pdfPath)) {
            $this->error("PDF not found at: {$pdfPath}");

            return self::FAILURE;
        }

        if ($from <= 0 || $to < $from) {
            $this->error('--from must be >= 1 and --to must be >= --from');

            return self::FAILURE;
        }

        // Parsing can be heavy; give PHP a little headroom.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $this->info("Parsing PDF: {$pdfPath} (pages {$from}-{$to}, lang={$lang})");

        // Parse PDF
        $parser = new Parser;
        try {
            $pdf = $parser->parseFile($pdfPath);
            $pages = $pdf->getPages();
        } catch (\Throwable $e) {
            $this->error('Failed to parse PDF: '.$e->getMessage());

            return self::FAILURE;
        }

        $totalPages = count($pages);
        if ($totalPages === 0) {
            $this->error('No pages found in the PDF.');

            return self::FAILURE;
        }
        if ($from > $totalPages) {
            $this->error("Start page {$from} exceeds total pages {$totalPages}.");

            return self::FAILURE;
        }
        $to = min($to, $totalPages);

        // Create/ensure node for AUC
        $section = ContentNode::firstOrCreate(
            ['slug' => 'african-union-commission-2023'],
            [
                'type' => 'section',
                'position' => 1,
                'status' => ContentNodeStatus::Draft,
                'edition' => '2023',
                'source_page_start' => $from,
                'source_page_end' => $to,
                'meta' => ['page_start' => $from, 'page_end' => $to],
            ]
        );

        $section->update([
            'edition' => '2023',
            'source_page_start' => $from,
            'source_page_end' => $to,
        ]);

        // Link canonical AU PDF
        Document::updateOrCreate(
            ['content_node_id' => $section->id, 'kind' => 'pdf', 'title' => 'AU Handbook 2023 (EN)'],
            [
                'external_url' => 'https://au.int/sites/default/files/documents/31829-doc-African_Union_Handbook_2023_ENGLISH.pdf',
                'page_start' => $from,
                'page_end' => $to,
                'meta' => ['page_start' => $from, 'page_end' => $to],
            ]
        );

        // Extract a curated text slice for search (keep it concise)
        $this->info('Extracting text …');
        $buffer = [];
        for ($i = $from; $i <= $to; $i++) {
            $pageObj = $pages[$i - 1] ?? null;
            if (! $pageObj) {
                continue;
            }
            $raw = $pageObj->getText();
            $buffer[] = $this->cleanText($raw);
        }

        $joined = trim(implode("\n\n", array_filter($buffer)));
        // Limit to ~5k chars to avoid huge rows; adjust if you need more for search
        $snippet = Str::limit($joined, 5000, ' …');

        ContentTranslation::updateOrCreate(
            ['content_node_id' => $section->id, 'locale' => $lang],
            ['title' => 'African Union Commission', 'body' => $snippet]
        );

        $this->info("Imported: African Union Commission (pages {$from}-{$to}, lang={$lang})");

        return self::SUCCESS;
    }

    /**
     * Normalize whitespace, strip odd control chars, and collapse runs.
     */
    protected function cleanText(string $text): string
    {
        // Replace non-breaking spaces and control chars with regular space
        $text = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}]+/u', ' ', $text) ?? $text;
        // Collapse multiple spaces/newlines
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        // Trim lines
        $lines = array_map(static fn ($l) => trim($l), explode("\n", $text));

        return trim(implode("\n", $lines));
    }
}
