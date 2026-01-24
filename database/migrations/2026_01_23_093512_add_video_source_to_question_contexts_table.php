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
        Schema::table('question_contexts', function (Blueprint $table) {
            $table->string('video_source')->nullable()->after('context_type'); // direct, youtube, vimeo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_contexts', function (Blueprint $table) {
            $table->dropColumn('video_source');
        });
    }
};
