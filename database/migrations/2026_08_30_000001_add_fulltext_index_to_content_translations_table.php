<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'content_translations_title_body_fulltext';

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('content_translations', function (Blueprint $table) {
            $table->fullText(['title', 'body'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('content_translations', function (Blueprint $table) {
            $table->dropFullText(self::INDEX_NAME);
        });
    }
};
