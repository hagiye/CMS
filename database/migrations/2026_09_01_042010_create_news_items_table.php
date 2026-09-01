<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('source_url')->unique();
            $table->string('source_domain')->default('au.int');
            $table->string('image_url')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('locale', 10)->default('en')->index();
            $table->string('status')->default('review')->index();
            $table->string('sync_mode')->default('source');
            $table->string('content_hash')->nullable()->index();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamp('source_changed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
