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
            $table->json('shared_content_text')->nullable()->after('instructions');
            $table->string('shared_content_video_type')->nullable()->after('media_type');
            $table->string('shared_content_video_url')->nullable()->after('shared_content_video_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropColumn(['shared_content_text', 'shared_content_video_type', 'shared_content_video_url']);
        });
    }
};
