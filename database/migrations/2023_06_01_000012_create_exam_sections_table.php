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
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('instructions')->nullable();
            $table->integer('order')->default(0);
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable(); // image, audio, video, reading_passage
            $table->integer('time_limit')->nullable(); // in minutes, null means no section time limit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
