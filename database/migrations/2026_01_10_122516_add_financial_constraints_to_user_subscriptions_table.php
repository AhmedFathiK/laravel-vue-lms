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
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Ensure one subscription per payment (1:1 relationship)
            $table->unique('payment_id');

            // Prevent exact duplicates (same user, plan, and start time)
            // This prevents "double-click" issues while allowing history (re-subscribing later)
            $table->unique(['user_id', 'subscription_plan_id', 'starts_at'], 'user_subs_plan_start_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
            $table->dropUnique('user_subs_plan_start_unique');
        });
    }
};
