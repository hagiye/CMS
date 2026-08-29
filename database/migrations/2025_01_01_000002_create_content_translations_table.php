<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_node_id')->constrained('content_nodes')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->longText('body')->nullable();
            $table->timestamps();
            $table->unique(['content_node_id','locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
