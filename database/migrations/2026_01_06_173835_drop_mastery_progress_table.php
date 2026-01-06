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
        Schema::dropIfExists('mastery_progress');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('mastery_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('revision_item_id')->constrained()->onDelete('cascade');
            $table->string('category');
            $table->text('description')->nullable();
            $table->integer('strength')->default(1);
            $table->timestamp('last_identified_at')->nullable();
            $table->timestamps();
        });
    }
};
