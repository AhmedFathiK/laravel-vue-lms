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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->onDelete('cascade');
            $table->json('title');
            $table->json('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('draft'); // draft, published, archived
            $table->string('video_url')->nullable();
            $table->boolean('reshow_incorrect_slides')->default(false);
            $table->integer('reshow_count')->default(1);
            $table->boolean('require_correct_answers')->default(false);
            $table->boolean('is_free')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
