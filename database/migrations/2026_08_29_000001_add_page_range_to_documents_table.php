<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('page_start')->nullable()->after('external_url');
            $table->unsignedInteger('page_end')->nullable()->after('page_start');
        });

        DB::table('documents')
            ->whereNotNull('meta')
            ->select(['id', 'meta'])
            ->orderBy('id')
            ->each(function ($document) {
                $meta = is_string($document->meta)
                    ? json_decode($document->meta, true)
                    : (array) $document->meta;

                $pageStart = filter_var($meta['page_start'] ?? null, FILTER_VALIDATE_INT);
                $pageEnd = filter_var($meta['page_end'] ?? null, FILTER_VALIDATE_INT);
                $updates = [];

                if ($pageStart !== false && $pageStart > 0) {
                    $updates['page_start'] = $pageStart;
                }

                if ($pageEnd !== false && $pageEnd > 0) {
                    $updates['page_end'] = $pageEnd;
                }

                if ($updates !== []) {
                    DB::table('documents')->where('id', $document->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['page_start', 'page_end']);
        });
    }
};
