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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('set null');
            $table->text('title')->nullable();
            $table->string('question_text');
            $table->string('type'); // mcq, matching, fill_blank, reordering, fill_blank_choices, writing
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable();
            $table->text('correct_feedback')->nullable();
            $table->text('incorrect_feedback')->nullable();
            $table->integer('points')->default(1);
            $table->string('difficulty')->default('medium'); // easy, medium, hard
            $table->json('tags')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable(); // image, audio, video
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
