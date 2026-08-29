<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_node_id')->nullable()->constrained('content_nodes')->nullOnDelete();
            $table->string('kind', 30); // pdf, image, link
            $table->string('title')->nullable();
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
