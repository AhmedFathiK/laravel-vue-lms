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
        Schema::table('exam_responses', function (Blueprint $table) {
            $table->foreignId('exam_question_id')->nullable()->after('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_responses', function (Blueprint $table) {
            $table->dropForeign(['exam_question_id']);
            $table->dropColumn('exam_question_id');
            $table->foreignId('question_id')->nullable(false)->change();
        });
    }
};
