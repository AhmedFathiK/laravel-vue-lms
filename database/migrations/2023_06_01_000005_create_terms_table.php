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
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('term');
            $table->json('definition');
            $table->json('translation')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable(); // image, video
            $table->timestamp('last_revision_date')->nullable();
            $table->timestamp('next_revision_date')->nullable();
            $table->integer('revision_counter')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
