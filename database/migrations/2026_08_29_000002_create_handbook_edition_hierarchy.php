<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EDITION_SLUG = 'au-handbook-2023';

    public function up(): void
    {
        $now = now();
        $editionId = DB::table('content_nodes')
            ->where('slug', self::EDITION_SLUG)
            ->value('id');

        if ($editionId === null) {
            $editionId = DB::table('content_nodes')->insertGetId([
                'parent_id' => null,
                'type' => 'edition',
                'slug' => self::EDITION_SLUG,
                'position' => 1,
                'status' => 'published',
                'published_at' => $now,
                'edition' => '2023',
                'revision' => 1,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('content_nodes')->where('id', $editionId)->update([
                'parent_id' => null,
                'type' => 'edition',
                'status' => 'published',
                'published_at' => DB::raw('COALESCE(published_at, CURRENT_TIMESTAMP)'),
                'edition' => '2023',
                'deleted_at' => null,
                'updated_at' => $now,
            ]);
        }

        DB::table('content_translations')->updateOrInsert(
            ['content_node_id' => $editionId, 'locale' => 'en'],
            [
                'title' => 'African Union Handbook 2023',
                'body' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('content_nodes')
            ->whereNull('parent_id')
            ->where('type', 'section')
            ->where('edition', '2023')
            ->where('id', '!=', $editionId)
            ->update(['parent_id' => $editionId]);

        $this->removeStructuralMetadata('content_nodes', [
            'parent_id',
            'type',
            'position',
            'status',
            'published_at',
            'edition',
            'page_start',
            'page_end',
            'source_page_start',
            'source_page_end',
            'revision',
        ]);
        $this->removeStructuralMetadata('documents', ['page_start', 'page_end']);

        Schema::table('content_nodes', function (Blueprint $table) {
            $table->index(['parent_id', 'type', 'position']);
            $table->index(['type', 'edition']);
        });
    }

    public function down(): void
    {
        $editionId = DB::table('content_nodes')
            ->where('slug', self::EDITION_SLUG)
            ->where('type', 'edition')
            ->value('id');

        if ($editionId !== null) {
            DB::table('content_nodes')->where('parent_id', $editionId)->update(['parent_id' => null]);
            DB::table('content_translations')->where('content_node_id', $editionId)->delete();
            DB::table('content_nodes')->where('id', $editionId)->delete();
        }

        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'type', 'position']);
            $table->dropIndex(['type', 'edition']);
        });
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function removeStructuralMetadata(string $table, array $keys): void
    {
        DB::table($table)
            ->whereNotNull('meta')
            ->select(['id', 'meta'])
            ->orderBy('id')
            ->each(function ($record) use ($table, $keys) {
                $meta = is_string($record->meta)
                    ? json_decode($record->meta, true)
                    : (array) $record->meta;

                if (! is_array($meta)) {
                    return;
                }

                foreach ($keys as $key) {
                    unset($meta[$key]);
                }

                DB::table($table)->where('id', $record->id)->update([
                    'meta' => $meta === [] ? null : json_encode($meta),
                ]);
            });
    }
};
