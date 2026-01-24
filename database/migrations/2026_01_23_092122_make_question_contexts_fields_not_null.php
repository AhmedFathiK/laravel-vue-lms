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
        Schema::table('question_contexts', function (Blueprint $table) {
            // Drop foreign key first to allow changing column
            $table->dropForeign(['course_id']);

            // Make columns NOT NULL
            $table->foreignId('course_id')->nullable(false)->change();
            $table->string('title')->nullable(false)->change();
            $table->string('context_type')->nullable(false)->change();

            // Re-add foreign key with cascade on delete (since it's required)
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_contexts', function (Blueprint $table) {
            $table->dropForeign(['course_id']);

            $table->foreignId('course_id')->nullable()->change();
            $table->string('title')->nullable()->change();
            $table->string('context_type')->nullable()->change();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('set null');
        });
    }
};
