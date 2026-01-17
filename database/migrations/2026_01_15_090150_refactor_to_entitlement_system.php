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
        // Disable FK checks to safely drop tables
        Schema::disableForeignKeyConstraints();

        // 1. Drop Legacy Tables
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plans');

        // 2. Clean Course Enrollments
        if (Schema::hasColumn('course_enrollments', 'user_subscription_id')) {
            Schema::table('course_enrollments', function (Blueprint $table) {
                $table->dropForeign(['user_subscription_id']);
                $table->dropColumn('user_subscription_id');
            });
        }

        // 3. Create New Schema

        // Billing Plans (The Offer)
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('USD');
            
            // Billing Logic
            $table->enum('billing_type', ['free', 'one_time', 'recurring']);
            $table->enum('billing_interval', ['month', 'year'])->nullable();
            
            // Access Logic (Template)
            $table->enum('access_type', ['lifetime', 'limited', 'while_active']);
            $table->integer('access_duration_days')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Features (The Capabilities)
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'course.access'
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Plan Features (The Promise)
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            
            // Scope (Polymorphic-ish, but explicit is often better. Let's use string type + ID)
            $table->string('scope_type')->nullable(); // e.g., 'App\Models\Course'
            $table->unsignedBigInteger('scope_id')->nullable();
            
            $table->string('value')->nullable(); // e.g., '5' downloads
            $table->timestamps();
            
            $table->index(['billing_plan_id', 'feature_id']);
        });

        // User Entitlements (The Grant)
        Schema::create('user_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('cancellation_reason')->nullable();
            $table->enum('status', ['pending', 'active', 'past_due', 'expired', 'failed', 'canceled', 'revoked'])->default('active');
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });

        // User Capabilities (The Snapshot / Cache)
        Schema::create('user_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_entitlement_id')->constrained('user_entitlements')->cascadeOnDelete();
            
            // Denormalized Feature Data
            $table->string('feature_code');
            $table->string('scope_type')->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('value')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_entitlement_id']);
            $table->index(['feature_code', 'scope_type', 'scope_id'], 'fast_access_check');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        
        Schema::dropIfExists('user_capabilities');
        Schema::dropIfExists('user_entitlements');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('billing_plans');

        // Note: We cannot easily restore data for dropped tables in 'down'
        // But we can recreate the structure if needed.
        // For dev mode, strict reversal isn't primary priority.
        
        Schema::enableForeignKeyConstraints();
    }
};
