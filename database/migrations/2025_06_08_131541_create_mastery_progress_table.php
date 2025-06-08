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
        Schema::create('mastery_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('revision_item_id')->constrained()->onDelete('cascade');

            // Mastery progress details
            $table->string('category'); // e.g., 'pronunciation', 'meaning', 'usage', etc.
            $table->text('description')->nullable(); // specific detail about what the user is working on
            $table->integer('strength')->default(1); // 1-10 scale where 1 is beginner, 10 is mastered
            $table->timestamp('last_identified_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'revision_item_id']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mastery_progress');
    }
};
