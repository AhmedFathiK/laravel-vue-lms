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
        // Pre-step: Remove dependency from exam_responses
        if (Schema::hasColumn('exam_responses', 'exam_question_id')) {
            Schema::table('exam_responses', function (Blueprint $table) {
                $table->dropForeign(['exam_question_id']);
                $table->dropColumn('exam_question_id');
            });
        }

        // 1. Create question_contexts table
        if (!Schema::hasTable('question_contexts')) {
            Schema::create('question_contexts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title')->nullable(); // Admin reference
                $table->longText('content')->nullable(); // For reading passages
                $table->string('media_type')->nullable(); // reading_passage, audio, video
                $table->string('media_url')->nullable(); // For audio/video
                $table->timestamps();
            });
        }

        // 2. Update questions table
        if (Schema::hasTable('questions') && !Schema::hasColumn('questions', 'question_context_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->foreignId('question_context_id')->nullable()->constrained('question_contexts')->nullOnDelete();
            });
        }

        // 3. Create exam_section_questions pivot table
        if (!Schema::hasTable('exam_section_questions')) {
            Schema::create('exam_section_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_section_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->integer('order')->default(0);
                $table->integer('points')->nullable(); // Override question default points
                $table->timestamps();
            });
        } else {
            // Ensure points column exists if table already existed
            if (!Schema::hasColumn('exam_section_questions', 'points')) {
                Schema::table('exam_section_questions', function (Blueprint $table) {
                    $table->integer('points')->nullable()->after('order');
                });
            }
        }

        // 4. Clean up exam_sections table
        if (Schema::hasTable('exam_sections')) {
            Schema::table('exam_sections', function (Blueprint $table) {
                $columns = ['media_url', 'shared_content_type', 'audio_url', 'shared_content_text', 'shared_content_video_type', 'shared_content_video_url'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('exam_sections', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // 5. Drop exam_questions table
        Schema::dropIfExists('exam_questions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive refactor
        Schema::dropIfExists('exam_section_questions');
        if (Schema::hasColumn('questions', 'question_context_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropForeign(['question_context_id']);
                $table->dropColumn('question_context_id');
            });
        }
        Schema::dropIfExists('question_contexts');
    }
};
