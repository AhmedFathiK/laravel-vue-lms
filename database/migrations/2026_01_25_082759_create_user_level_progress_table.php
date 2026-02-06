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
        Schema::create('user_level_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // Safety Rule: Prevent deleting a level if user progress exists
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            
            $table->enum('status', ['locked', 'unlocked', 'in_progress', 'completed', 'skipped'])
                  ->default('locked');
            
            $table->foreignId('source_attempt_id')
                  ->nullable()
                  ->constrained('exam_attempts')
                  ->nullOnDelete();

            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'level_id']);
            $table->index(['user_id', 'course_id']); // Optimization for fetching course progress
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_level_progress');
    }
};
