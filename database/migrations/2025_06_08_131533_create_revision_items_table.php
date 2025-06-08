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
        Schema::create('revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('revisionable'); // For polymorphic relationship with Term or Concept

            // FSRS algorithm fields
            $table->float('difficulty')->default(5.0); // 1-10 scale, default middle difficulty
            $table->float('stability')->default(0.0);
            $table->integer('interval')->default(1); // Days until next review
            $table->timestamp('due_date')->nullable();
            $table->timestamp('last_review')->nullable();
            $table->integer('review_count')->default(0);
            $table->integer('lapse_count')->default(0);
            $table->string('state')->default('new'); // new, learning, review, relearning
            $table->float('retrievability')->nullable(); // Current probability of recall (0-1)

            // History tracking
            $table->json('response_history')->nullable(); // Store historical responses

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['user_id', 'due_date']);
            $table->index(['revisionable_type', 'revisionable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revision_items');
    }
};
