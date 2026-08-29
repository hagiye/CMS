<?php

namespace Tests\Unit;

use App\Services\HandbookPdfSegmenter;
use PHPUnit\Framework\TestCase;

class HandbookPdfSegmenterTest extends TestCase
{
    public function test_it_creates_meaningful_segments_and_removes_repeated_page_furniture(): void
    {
        $segments = (new HandbookPdfSegmenter)->segment([
            [
                'number' => 10,
                'text' => "AFRICAN UNION HANDBOOK\nGovernance and Structure\nThe Commission coordinates the Union's work.\n10",
            ],
            [
                'number' => 11,
                'text' => "AFRICAN UNION HANDBOOK\nLeadership\nThe Chairperson leads the Commission.\n11",
            ],
            [
                'number' => 12,
                'text' => "AFRICAN UNION HANDBOOK\nThis paragraph continues the leadership discussion.\n12",
            ],
        ]);

        $this->assertCount(2, $segments);
        $this->assertSame('Governance and Structure', $segments[0]['title']);
        $this->assertSame(10, $segments[0]['page_start']);
        $this->assertSame('Leadership', $segments[1]['title']);
        $this->assertSame(11, $segments[1]['page_start']);
        $this->assertSame(12, $segments[1]['page_end']);
        $this->assertStringContainsString('continues the leadership discussion', $segments[1]['body']);
        $this->assertStringNotContainsString('AFRICAN UNION HANDBOOK', implode(' ', array_column($segments, 'body')));
    }

    public function test_a_page_without_a_heading_is_kept_as_a_reviewable_fallback_segment(): void
    {
        $segments = (new HandbookPdfSegmenter)->segment([
            ['number' => 3, 'text' => 'A paragraph of extracted content without a detected heading.'],
        ]);

        $this->assertCount(1, $segments);
        $this->assertSame('Page 3', $segments[0]['title']);
        $this->assertSame(3, $segments[0]['page_start']);
        $this->assertSame(3, $segments[0]['page_end']);
    }
}
