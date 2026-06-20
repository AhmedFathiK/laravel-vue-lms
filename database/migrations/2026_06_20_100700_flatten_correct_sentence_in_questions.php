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
        DB::table('questions')
            ->whereNotNull('correct_sentence')
            ->get()
            ->each(function ($question) {
                $value = $question->correct_sentence;

                // If it looks like JSON, try to flatten it
                if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        // Take 'en' or first available
                        $flattened = $decoded['en'] ?? reset($decoded) ?? '';

                        DB::table('questions')
                            ->where('id', $question->id)
                            ->update(['correct_sentence' => $flattened]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to re-jsonify without knowing original locales,
        // but we can wrap it back in 'en' if we really wanted to.
    }
};
