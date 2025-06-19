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
            $table->string('media_type')->nullable(); // image, image_audio, video
            $table->string('audio_url')->nullable(); // For image_audio type, stores the audio file URL
            $table->json('example')->nullable(); // Example text with translations
            $table->string('example_audio_url')->nullable(); // Audio pronunciation of the example
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
