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
        Schema::create('user_trophies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trophy_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('context')->nullable(); // Additional context about how the trophy was earned
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'trophy_id']);
            $table->index(['user_id', 'course_id']);

            // Unique constraint to prevent duplicate trophies
            $table->unique(['user_id', 'trophy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_trophies');
    }
};
