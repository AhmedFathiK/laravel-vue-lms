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
            // Add unique index on (user_id, subscription_plan_id, status)
            // Note: The user request said (user_id, course_id, status), but user_subscriptions table 
            // links to subscription_plan_id, not directly to course_id (though plan has course_id).
            // Looking at the table schema: user_id, subscription_plan_id, payment_id...
            // It seems better to enforce uniqueness on the plan + status, OR we need to join to ensure course uniqueness.
            // However, typical DB unique constraints are on columns in the table.
            // If the goal is "prevent multiple active subscriptions for the same course", we might face a challenge 
            // if we only have subscription_plan_id.
            // BUT, usually one plan belongs to one course.
            // So if we constrain (user_id, subscription_plan_id, status), it prevents duplicate active subs for the SAME plan.
            // If there are multiple plans for the same course, this index won't prevent having one active sub for Plan A (Course X) and one for Plan B (Course X).
            // To strictly enforce "one active sub per course", we'd need a trigger or application logic (which we added in Service).
            // 
            // However, the user explicitly asked for: "Add a unique composite index on (user_id, course_id, status)"
            // BUT `course_id` does not exist in `user_subscriptions` table based on previous `SearchCodebase` result (migration 2024_06_10_000004).
            // It has `subscription_plan_id`.
            // Let's check if I should add `course_id` column or just index `subscription_plan_id`.
            //
            // Re-reading user request: "user_subscriptions table Add a unique composite index on (user_id, course_id, status)"
            // If the column doesn't exist, I can't index it. 
            // Let's assume the user meant `subscription_plan_id` OR I should denormalize and add `course_id` for this purpose.
            // Given the instruction "Minimal code-level guards", maybe I should stick to what's possible.
            // BUT, if I look at `CourseEnrollment`, it has `course_id`.
            //
            // Let's use `subscription_plan_id` as the proxy for now, but to be safe, I'll add a comment.
            // actually, if I want to be strictly following "user_id, course_id, status", I would need to add course_id.
            // But adding a column involves backfilling data.
            // 
            // Let's look at the `createSubscription` method in Service. It checks:
            // whereHas('plan', function ($query) use ($plan) { $query->where('course_id', $plan->course_id); })
            // So the application logic handles the "same course" check.
            // The DB constraint is a safety net. 
            // If I only index `subscription_plan_id`, it protects against double-clicks on the SAME plan.
            // This is valuable.
            
            $table->unique(['user_id', 'subscription_plan_id', 'status'], 'user_subs_plan_status_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropUnique('user_subs_plan_status_unique');
        });
    }
};
