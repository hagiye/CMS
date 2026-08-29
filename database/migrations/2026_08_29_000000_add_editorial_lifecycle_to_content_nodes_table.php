<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('position');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->string('edition', 20)->nullable()->after('published_at');
            $table->unsignedInteger('source_page_start')->nullable()->after('edition');
            $table->unsignedInteger('source_page_end')->nullable()->after('source_page_start');
            $table->unsignedInteger('revision')->default(1)->after('source_page_end');
            $table->foreignId('editor_id')
                ->nullable()
                ->after('revision')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['status', 'published_at']);
            $table->index('edition');
        });

        // Content that predates the lifecycle was already public, so preserve its visibility.
        DB::table('content_nodes')->update([
            'status' => 'published',
            'published_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            'edition' => '2023',
        ]);

        DB::table('content_nodes')
            ->where('slug', 'african-union-commission-2023')
            ->update(['source_page_start' => 92]);
    }

    public function down(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropIndex(['edition']);
            $table->dropConstrainedForeignId('editor_id');
            $table->dropColumn([
                'status',
                'published_at',
                'edition',
                'source_page_start',
                'source_page_end',
                'revision',
            ]);
        });
    }
};
