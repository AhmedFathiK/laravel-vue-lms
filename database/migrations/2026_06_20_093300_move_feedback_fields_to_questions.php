<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add new columns to questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->text('correct_sentence')->nullable()->after('incorrect_feedback');
            $table->text('correct_sentence_translation')->nullable()->after('correct_sentence'); // Translatable (json in DB)
        });

        // 2. Migrate data from slides to questions
        DB::table('slides')
            ->whereNotNull('question_id')
            ->where(function($query) {
                $query->whereNotNull('feedback_sentence')
                      ->orWhereNotNull('feedback_translation');
            })
            ->get()
            ->each(function ($slide) {
                DB::table('questions')
                    ->where('id', $slide->question_id)
                    ->update([
                        'correct_sentence' => $slide->feedback_sentence,
                        'correct_sentence_translation' => $slide->feedback_translation,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Copy back if needed (optional, but good for safety)
        DB::table('questions')
            ->whereNotNull('correct_sentence')
            ->orWhereNotNull('correct_sentence_translation')
            ->get()
            ->each(function ($question) {
                DB::table('slides')
                    ->where('question_id', $question->id)
                    ->update([
                        'feedback_sentence' => $question->correct_sentence,
                        'feedback_translation' => $question->correct_sentence_translation,
                    ]);
            });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['correct_sentence', 'correct_sentence_translation']);
        });
    }
};
