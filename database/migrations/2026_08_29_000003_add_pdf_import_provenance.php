<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('page_end')->index();
            $table->string('original_filename')->nullable()->after('checksum');
            $table->timestamp('imported_at')->nullable()->after('original_filename');
        });

        Schema::table('content_nodes', function (Blueprint $table) {
            $table->foreignId('source_document_id')
                ->nullable()
                ->after('source_page_end')
                ->constrained('documents')
                ->nullOnDelete();
            $table->string('import_key', 64)->nullable()->after('source_document_id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropUnique(['import_key']);
            $table->dropConstrainedForeignId('source_document_id');
            $table->dropColumn('import_key');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['checksum']);
            $table->dropColumn(['checksum', 'original_filename', 'imported_at']);
        });
    }
};
