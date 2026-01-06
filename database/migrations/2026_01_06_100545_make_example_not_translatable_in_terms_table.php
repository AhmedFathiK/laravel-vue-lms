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
        Schema::table('terms', function (Blueprint $table) {
            $table->text('example')->nullable()->change();
        });

        // Now handle the data migration for 'example' if it was JSON
        $terms = DB::table('terms')->get();
        foreach ($terms as $term) {
            $exampleData = json_decode($term->example, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($exampleData)) {
                // Get the 'en' translation or the first available one
                $newValue = $exampleData['en'] ?? (reset($exampleData) ?: '');
                DB::table('terms')->where('id', $term->id)->update(['example' => $newValue]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->text('example')->nullable()->change();
        });
    }
};
