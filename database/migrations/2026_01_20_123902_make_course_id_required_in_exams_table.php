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
        // Delete orphan exams before making course_id required
        \Illuminate\Support\Facades\DB::table('exams')->whereNull('course_id')->delete();

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->change();
        });
    }
};
