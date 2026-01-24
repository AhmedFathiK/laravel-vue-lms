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
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('final_exam_id')->nullable()->constrained('exams')->nullOnDelete();
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->foreignId('final_exam_id')->nullable()->constrained('exams')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['final_exam_id']);
            $table->dropColumn('final_exam_id');
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->dropForeign(['final_exam_id']);
            $table->dropColumn('final_exam_id');
        });
    }
};
