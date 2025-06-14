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
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Add new fields
            $table->foreignId('course_id')->nullable()->after('id')->constrained('courses')->cascadeOnDelete();
            $table->enum('plan_type', ['recurring', 'one-time', 'free'])->default('one-time')->after('billing_cycle');
            $table->boolean('is_free')->default(false)->after('plan_type');

            // For level-based access
            $table->json('accessible_levels')->nullable()->after('is_free');

            // Change price to nullable for free plans
            $table->decimal('price', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
            $table->dropColumn('plan_type');
            $table->dropColumn('is_free');
            $table->dropColumn('accessible_levels');

            // Revert price to non-nullable
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });
    }
};
