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
        Schema::create('trophies', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->string('icon_url')->nullable(); // Path to the trophy icon image
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('trigger_type'); // e.g., 'completed_lesson', 'quiz_score', 'level_completed'
            $table->integer('trigger_repeat_count')->default(1); // Number of times the trigger must be met
            $table->json('requirements')->nullable(); // JSON with specific requirements
            $table->integer('points')->default(0); // Points awarded for earning this trophy
            $table->integer('points_threshold')->nullable(); // Points required for point-based trophies
            $table->string('rarity')->default('common'); // common, uncommon, rare, epic, legendary
            $table->boolean('is_hidden')->default(false); // Hidden trophies are not shown until earned
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('trigger_type');
            $table->index(['course_id', 'trigger_type']);
            $table->index('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trophies');
    }
};
