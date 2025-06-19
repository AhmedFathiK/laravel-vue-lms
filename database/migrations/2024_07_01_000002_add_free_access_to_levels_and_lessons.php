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
        Schema::table('levels', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('is_unlocked');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('status');
        });

        // Add is_free to courses table as well
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
