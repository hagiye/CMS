<?php

namespace Database\Seeders;

use App\Enums\ContentNodeStatus;
use App\Models\ContentNode;
use App\Models\ContentTranslation;
use App\Models\Link;
use Illuminate\Database\Seeder;

class HandbookSeeder extends Seeder
{
    public function run(): void
    {
        $edition = ContentNode::firstOrCreate(
            ['slug' => 'au-handbook-2023'],
            [
                'type' => 'edition',
                'position' => 1,
                'status' => ContentNodeStatus::Published,
                'published_at' => now(),
                'edition' => '2023',
            ],
        );

        ContentTranslation::updateOrCreate(
            ['content_node_id' => $edition->id, 'locale' => 'en'],
            ['title' => 'African Union Handbook 2023', 'body' => null],
        );

        $sections = [
            ['slug' => 'member-states', 'title' => 'Member States', 'position' => 1],
            ['slug' => 'au-structure', 'title' => 'African Union Structure', 'position' => 2],
            ['slug' => 'assembly', 'title' => 'Assembly', 'position' => 3],
            ['slug' => 'executive-council', 'title' => 'Executive Council', 'position' => 4],
            ['slug' => 'permanent-representatives-committee', 'title' => 'Permanent Representatives’ Committee (PRC)', 'position' => 5],
            ['slug' => 'stcs', 'title' => 'Specialized Technical Committees (STCs)', 'position' => 6],
            ['slug' => 'peace-and-security-council', 'title' => 'Peace & Security Council (PSC) and APSA', 'position' => 7],
            ['slug' => 'african-union-commission-2023', 'title' => 'African Union Commission', 'position' => 8, 'source_page_start' => 92],
            ['slug' => 'pan-african-parliament', 'title' => 'Pan-African Parliament (PAP)', 'position' => 9],
            ['slug' => 'ecossoc', 'title' => 'ECOSOCC', 'position' => 10],
        ];

        foreach ($sections as $section) {
            $node = ContentNode::firstOrCreate(
                ['slug' => $section['slug']],
                [
                    'parent_id' => $edition->id,
                    'type' => 'section',
                    'position' => $section['position'],
                    'status' => ContentNodeStatus::Published,
                    'published_at' => now(),
                    'edition' => '2023',
                    'source_page_start' => $section['source_page_start'] ?? null,
                ],
            );

            if ($node->parent_id !== $edition->id) {
                $node->update(['parent_id' => $edition->id]);
            }

            ContentTranslation::updateOrCreate(
                ['content_node_id' => $node->id, 'locale' => 'en'],
                ['title' => $section['title'], 'body' => null],
            );
        }

        $commission = ContentNode::where('slug', 'african-union-commission-2023')->first();

        if ($commission) {
            Link::updateOrCreate(
                ['content_node_id' => $commission->id, 'label' => 'Open AU Handbook (page 92)'],
                ['url' => 'https://au.int/sites/default/files/documents/31829-doc-African_Union_Handbook_2023_ENGLISH.pdf#page=92'],
            );
        }
    }
}
