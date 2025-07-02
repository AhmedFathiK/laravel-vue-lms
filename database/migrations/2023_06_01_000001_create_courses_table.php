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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_category_id')->nullable()->constrained('course_categories')->nullOnDelete();
            $table->string('main_locale')->default('en');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('status')->default('draft'); // draft, published, archived
            $table->string('thumbnail')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_free')->default(true);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('subscription_type')->default('one-time'); // one-time, monthly
            $table->string('leaderboard_reset_frequency')->default('monthly'); // never, weekly, monthly
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
