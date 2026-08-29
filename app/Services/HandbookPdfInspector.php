<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class HandbookPdfInspector
{
    public function __construct(
        private readonly Parser $parser,
        private readonly HandbookPdfSegmenter $segmenter,
    ) {}

    /**
     * @return array{
     *     total_pages: int,
     *     from: int,
     *     to: int,
     *     pages: array<int, array{number: int, text: string}>,
     *     failures: array<int, string>,
     *     segments: array<int, array{title: string, body: string, page_start: int, page_end: int}>
     * }
     */
    public function inspect(string $pdfPath, int $from, int $to): array
    {
        try {
            $pdf = $this->parser->parseFile($pdfPath);
            $pdfPages = $pdf->getPages();
        } catch (Throwable $exception) {
            throw new RuntimeException('Failed to parse PDF: '.$exception->getMessage(), previous: $exception);
        }

        $totalPages = count($pdfPages);

        if ($totalPages === 0) {
            throw new RuntimeException('No pages were found in the PDF.');
        }

        if ($from > $totalPages) {
            throw new RuntimeException("Start page {$from} exceeds the PDF's {$totalPages} pages.");
        }

        $actualTo = min($to, $totalPages);
        $pages = [];
        $failures = [];

        for ($pageNumber = $from; $pageNumber <= $actualTo; $pageNumber++) {
            $page = $pdfPages[$pageNumber - 1] ?? null;

            if ($page === null) {
                $failures[$pageNumber] = 'Page object was not available.';

                continue;
            }

            try {
                $text = $this->cleanText((string) $page->getText());
            } catch (Throwable $exception) {
                $failures[$pageNumber] = $exception->getMessage();

                continue;
            }

            if ($text === '') {
                $failures[$pageNumber] = 'No extractable text was found.';

                continue;
            }

            $pages[] = ['number' => $pageNumber, 'text' => $text];
        }

        return [
            'total_pages' => $totalPages,
            'from' => $from,
            'to' => $actualTo,
            'pages' => $pages,
            'failures' => $failures,
            'segments' => $this->segmenter->segment($pages),
        ];
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $lines = array_map(function (string $line): string {
            return trim(preg_replace('/[ \t]+/u', ' ', $line) ?? $line);
        }, explode("\n", $text));

        return trim(preg_replace("/\n{3,}/u", "\n\n", implode("\n", $lines)) ?? implode("\n", $lines));
    }
}
