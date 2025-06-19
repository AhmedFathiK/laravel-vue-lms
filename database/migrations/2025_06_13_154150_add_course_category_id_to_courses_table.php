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
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('course_category_id')->nullable()->after('id')->constrained('course_categories')->nullOnDelete();
            $table->decimal('price', 10, 2)->default(0)->after('is_free');
            $table->string('subscription_type')->default('one-time')->after('price'); // one-time, monthly
            $table->string('leaderboard_reset_frequency')->default('monthly')->after('subscription_type'); // never, weekly, monthly
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['course_category_id']);
            $table->dropColumn('course_category_id');
            $table->dropColumn('price');
            $table->dropColumn('subscription_type');
            $table->dropColumn('leaderboard_reset_frequency');
        });
    }
};
