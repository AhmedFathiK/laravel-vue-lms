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
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('reset_frequency')->default('monthly'); // daily, weekly, monthly, yearly, all_time
            $table->boolean('is_active')->default(true);
            $table->boolean('keep_history')->default(false); // Whether to keep history after reset
            $table->integer('max_entries')->default(100); // Maximum number of entries to display
            $table->timestamps();

            // Indexes
            $table->index(['course_id', 'reset_frequency']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
