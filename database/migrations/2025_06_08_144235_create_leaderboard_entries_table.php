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
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leaderboard_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('points')->default(0);
            $table->integer('rank')->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->timestamp('period_start')->nullable(); // For archived entries
            $table->timestamp('period_end')->nullable(); // For archived entries
            $table->timestamps();

            // Indexes
            $table->index(['leaderboard_id', 'rank']);
            $table->index(['leaderboard_id', 'user_id']);
            $table->index(['user_id', 'leaderboard_id']);

            // Unique constraint to prevent duplicate entries
            $table->unique(['leaderboard_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};
