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
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->renameColumn('media_type', 'shared_content_type');
        });

        // Ensure shared_content_type is not null after rename if we want to enforce it, 
        // but since existing data might have nulls, we'll keep it nullable for now or set a default.
        // For strictness, we should ideally have a default or migrate data.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->renameColumn('shared_content_type', 'media_type');
        });
    }
};
