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
        // Add active_course_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_course_id')->nullable()->constrained('courses')->onDelete('set null');
        });

        // Create user_streaks table
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->integer('current_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_course_id']);
            $table->dropColumn('active_course_id');
        });

        Schema::dropIfExists('user_streaks');
    }
};
