<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, handle the data migration for 'term' if it's currently JSON
        $terms = DB::table('terms')->get();
        foreach ($terms as $term) {
            $termData = json_decode($term->term, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($termData)) {
                // Get the 'en' translation or the first available one
                $newValue = $termData['en'] ?? (reset($termData) ?: '');
                DB::table('terms')->where('id', $term->id)->update(['term' => $newValue]);
            }
        }

        Schema::table('terms', function (Blueprint $table) {
            $table->string('term')->change();
            $table->renameColumn('definition', 'meaning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->renameColumn('meaning', 'definition');
            $table->text('term')->change();
        });
    }
};
