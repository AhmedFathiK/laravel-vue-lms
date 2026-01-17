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
        // Drop legacy foreign keys and columns from course_enrollments
        if (Schema::hasTable('course_enrollments')) {
            Schema::table('course_enrollments', function (Blueprint $table) {
                if (Schema::hasColumn('course_enrollments', 'user_subscription_id')) {
                    // Attempt to drop foreign key first. 
                    // We try-catch or assume standard naming convention if we could, 
                    // but dropForeign(['column']) is the standard Laravel way.
                    try {
                        $table->dropForeign(['user_subscription_id']);
                    } catch (\Exception $e) {
                        // Foreign key might not exist or has different name, continue
                    }
                    $table->dropColumn('user_subscription_id');
                }
            });
        }

        // Drop legacy tables
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');

        // Drop legacy is_free columns
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (Schema::hasColumn('courses', 'is_free')) {
                    $table->dropColumn('is_free');
                }
            });
        }

        if (Schema::hasTable('levels')) {
            Schema::table('levels', function (Blueprint $table) {
                if (Schema::hasColumn('levels', 'is_free')) {
                    $table->dropColumn('is_free');
                }
            });
        }

        if (Schema::hasTable('lessons')) {
            Schema::table('lessons', function (Blueprint $table) {
                if (Schema::hasColumn('lessons', 'is_free')) {
                    $table->dropColumn('is_free');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We do not support rolling back this hard cutover.
        // Data loss is expected and intentional.
    }
};
