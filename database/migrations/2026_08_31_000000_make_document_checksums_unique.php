<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')
            ->whereNotNull('checksum')
            ->where('checksum', '<>', '')
            ->orderBy('id')
            ->get()
            ->groupBy('checksum')
            ->filter(fn ($documents): bool => $documents->count() > 1)
            ->each(function ($documents): void {
                $canonical = $documents->first();
                $duplicateIds = $documents->skip(1)->pluck('id');
                $firstPresent = fn (string $column) => $documents
                    ->pluck($column)
                    ->first(fn ($value): bool => $value !== null && $value !== '');

                DB::table('content_nodes')
                    ->whereIn('source_document_id', $duplicateIds)
                    ->update(['source_document_id' => $canonical->id]);

                DB::table('documents')->where('id', $canonical->id)->update([
                    'content_node_id' => $firstPresent('content_node_id'),
                    'kind' => $firstPresent('kind'),
                    'title' => $firstPresent('title'),
                    'path' => $firstPresent('path'),
                    'external_url' => $firstPresent('external_url'),
                    'page_start' => $documents->pluck('page_start')->filter(fn ($page) => $page !== null)->min(),
                    'page_end' => $documents->pluck('page_end')->filter(fn ($page) => $page !== null)->max(),
                    'original_filename' => $firstPresent('original_filename'),
                    'imported_at' => $firstPresent('imported_at'),
                    'meta' => $firstPresent('meta'),
                    'updated_at' => now(),
                ]);

                DB::table('documents')->whereIn('id', $duplicateIds)->delete();
            });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['checksum']);
            $table->unique('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique(['checksum']);
            $table->index('checksum');
        });
    }
};
