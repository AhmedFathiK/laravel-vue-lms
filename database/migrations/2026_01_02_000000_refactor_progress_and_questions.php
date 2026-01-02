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
        // Drop the old learner_progress table
        Schema::dropIfExists('learner_progress');

        // Create pivot table for Questions <-> Terms
        Schema::create('question_term', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('term_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['question_id', 'term_id']);
        });

        // Create pivot table for Questions <-> Concepts
        Schema::create('question_concept', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('concept_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['question_id', 'concept_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_concept');
        Schema::dropIfExists('question_term');

        // Recreate learner_progress table (schema from deleted migration)
        Schema::create('learner_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('level_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('lesson_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('slide_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('is_completed')->default(false);
            $table->json('response_data')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slide_id'], 'user_slide_unique');
        });
    }
};
