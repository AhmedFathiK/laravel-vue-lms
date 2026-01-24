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
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_section_id')->constrained()->onDelete('cascade');
            $table->text('question_text');
            $table->string('type'); // mcq, matching, fill_blank, reordering, fill_blank_choices, writing
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable(); // Server-side only
            $table->text('correct_feedback')->nullable();
            $table->text('incorrect_feedback')->nullable();
            $table->integer('marks')->default(1);
            $table->integer('order')->default(0);
            $table->boolean('is_exam_only')->default(true);
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
        Schema::dropIfExists('exam_questions');
    }
};
