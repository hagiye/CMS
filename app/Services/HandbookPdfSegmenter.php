<?php

namespace App\Services;

class HandbookPdfSegmenter
{
    private const MAX_SEGMENT_CHARACTERS = 12000;

    /**
     * @param  array<int, array{number: int, text: string}>  $pages
     * @return array<int, array{title: string, body: string, page_start: int, page_end: int}>
     */
    public function segment(array $pages): array
    {
        $preparedPages = $this->preparePages($pages);
        $segments = [];
        $current = null;

        foreach ($preparedPages as $page) {
            $headings = array_filter($page['lines'], fn (string $line): bool => $this->isHeading($line));

            if ($headings === []) {
                if ($current !== null && ! $current['fallback']) {
                    foreach ($page['lines'] as $line) {
                        $current['body'][] = ['page' => $page['number'], 'text' => $line];
                    }
                    $current['page_end'] = $page['number'];

                    continue;
                }

                $this->finishSegment($segments, $current);
                $current = [
                    'title' => "Page {$page['number']}",
                    'page_start' => $page['number'],
                    'page_end' => $page['number'],
                    'fallback' => true,
                    'body' => array_map(
                        fn (string $line): array => ['page' => $page['number'], 'text' => $line],
                        $page['lines'],
                    ),
                ];
                $this->finishSegment($segments, $current);

                continue;
            }

            foreach ($page['lines'] as $line) {
                if ($this->isHeading($line)) {
                    if ($current !== null && $current['body'] === []) {
                        $current['title'] = $this->normalizeHeading($line);
                        $current['page_start'] = $page['number'];
                        $current['page_end'] = $page['number'];

                        continue;
                    }

                    $this->finishSegment($segments, $current);
                    $current = [
                        'title' => $this->normalizeHeading($line),
                        'page_start' => $page['number'],
                        'page_end' => $page['number'],
                        'fallback' => false,
                        'body' => [],
                    ];

                    continue;
                }

                if ($current === null) {
                    $current = [
                        'title' => "Page {$page['number']}",
                        'page_start' => $page['number'],
                        'page_end' => $page['number'],
                        'fallback' => true,
                        'body' => [],
                    ];
                }

                $current['body'][] = ['page' => $page['number'], 'text' => $line];
                $current['page_end'] = $page['number'];
            }
        }

        $this->finishSegment($segments, $current);

        return $segments;
    }

    /**
     * @param  array<int, array{number: int, text: string}>  $pages
     * @return array<int, array{number: int, lines: array<int, string>}>
     */
    private function preparePages(array $pages): array
    {
        $lineCounts = [];
        $prepared = [];

        foreach ($pages as $page) {
            $lines = array_values(array_filter(
                array_map('trim', preg_split('/\R/u', $page['text']) ?: []),
                fn (string $line): bool => $line !== '' && ! preg_match('/^\d+$/', $line),
            ));
            $prepared[] = ['number' => $page['number'], 'lines' => $lines];

            foreach (array_unique(array_map([$this, 'normalizedLineKey'], $lines)) as $key) {
                if ($key !== '') {
                    $lineCounts[$key] = ($lineCounts[$key] ?? 0) + 1;
                }
            }
        }

        $repeatThreshold = max(2, (int) ceil(count($pages) * 0.6));
        $repeatedLines = array_keys(array_filter(
            $lineCounts,
            fn (int $count): bool => $count >= $repeatThreshold,
        ));

        foreach ($prepared as &$page) {
            $page['lines'] = array_values(array_filter(
                $page['lines'],
                fn (string $line): bool => ! in_array($this->normalizedLineKey($line), $repeatedLines, true),
            ));
        }
        unset($page);

        return $prepared;
    }

    private function isHeading(string $line): bool
    {
        $line = trim($line);
        $length = mb_strlen($line);
        $words = preg_split('/\s+/u', $line) ?: [];

        if ($length < 4 || $length > 120 || count($words) > 14 || preg_match('/[.!?;]$/u', $line)) {
            return false;
        }

        if (preg_match('/^(?:chapter|section|part|annex|appendix)\b/iu', $line)) {
            return true;
        }

        if (preg_match('/^\d+(?:\.\d+)*[.)]?\s+\p{L}/u', $line)) {
            return true;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';

        if (mb_strlen($letters) >= 4 && $letters === mb_strtoupper($letters)) {
            return true;
        }

        $significantWords = array_values(array_filter(
            $words,
            fn (string $word): bool => ! in_array(
                mb_strtolower(trim($word, " \\t\\n\\r\\0\\x0B,:'\"-")),
                ['a', 'an', 'and', 'at', 'by', 'for', 'from', 'in', 'of', 'on', 'or', 'the', 'to', 'with'],
                true,
            ),
        ));

        if ($significantWords === []) {
            return false;
        }

        $titleCaseWords = array_filter($significantWords, function (string $word): bool {
            $firstLetter = mb_substr(preg_replace('/^[^\p{L}]+/u', '', $word) ?? '', 0, 1);

            return $firstLetter !== '' && $firstLetter === mb_strtoupper($firstLetter);
        });

        return count($titleCaseWords) / count($significantWords) >= 0.75;
    }

    private function normalizeHeading(string $heading): string
    {
        return trim(preg_replace('/\s+/u', ' ', rtrim($heading, ':')) ?? $heading);
    }

    private function normalizedLineKey(string $line): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $line) ?? $line));
    }

    /**
     * @param  array<int, array{title: string, body: string, page_start: int, page_end: int}>  $segments
     * @param  array{title: string, page_start: int, page_end: int, fallback: bool, body: array<int, array{page: int, text: string}>}|null  $current
     */
    private function finishSegment(array &$segments, ?array &$current): void
    {
        if ($current === null) {
            return;
        }

        if ($current['body'] === []) {
            $current = null;

            return;
        }

        $chunks = [];
        $chunk = [];
        $chunkLength = 0;

        foreach ($current['body'] as $line) {
            $lineLength = mb_strlen($line['text']) + 1;

            if ($chunk !== [] && $chunkLength + $lineLength > self::MAX_SEGMENT_CHARACTERS) {
                $chunks[] = $chunk;
                $chunk = [];
                $chunkLength = 0;
            }

            $chunk[] = $line;
            $chunkLength += $lineLength;
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        foreach ($chunks as $index => $lines) {
            $segments[] = [
                'title' => count($chunks) > 1 ? $current['title'].' (Part '.($index + 1).')' : $current['title'],
                'body' => trim(implode("\n", array_column($lines, 'text'))),
                'page_start' => min(array_column($lines, 'page')),
                'page_end' => max(array_column($lines, 'page')),
            ];
        }

        $current = null;
    }
}
