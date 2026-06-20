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
        if (Schema::hasTable('questions')) {
            Schema::table('questions', function (Blueprint $table) {
                if (Schema::hasColumn('questions', 'correct_feedback')) {
                    $table->renameColumn('correct_feedback', 'correct_answer_feedback');
                }
                if (Schema::hasColumn('questions', 'incorrect_feedback')) {
                    $table->renameColumn('incorrect_feedback', 'incorrect_answer_feedback');
                }
            });
        }

        if (Schema::hasTable('exam_questions')) {
            Schema::table('exam_questions', function (Blueprint $table) {
                if (Schema::hasColumn('exam_questions', 'correct_feedback')) {
                    $table->renameColumn('correct_feedback', 'correct_answer_feedback');
                }
                if (Schema::hasColumn('exam_questions', 'incorrect_feedback')) {
                    $table->renameColumn('incorrect_feedback', 'incorrect_answer_feedback');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('questions')) {
            Schema::table('questions', function (Blueprint $table) {
                if (Schema::hasColumn('questions', 'correct_answer_feedback')) {
                    $table->renameColumn('correct_answer_feedback', 'correct_feedback');
                }
                if (Schema::hasColumn('questions', 'incorrect_answer_feedback')) {
                    $table->renameColumn('incorrect_answer_feedback', 'incorrect_feedback');
                }
            });
        }

        if (Schema::hasTable('exam_questions')) {
            Schema::table('exam_questions', function (Blueprint $table) {
                if (Schema::hasColumn('exam_questions', 'correct_answer_feedback')) {
                    $table->renameColumn('correct_answer_feedback', 'correct_feedback');
                }
                if (Schema::hasColumn('exam_questions', 'incorrect_answer_feedback')) {
                    $table->renameColumn('incorrect_answer_feedback', 'incorrect_feedback');
                }
            });
        }
    }
};
