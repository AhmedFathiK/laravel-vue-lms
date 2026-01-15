<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $student;
    protected $course;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Payment
        config(['services.payment.supported_currencies' => 'EGP,USD']);
        config(['services.payment.default_currency' => 'EGP']);
        
        // Setup Roles
        if (!\Spatie\Permission\Models\Role::where('name', 'Admin')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'Admin']);
        }
        if (!\Spatie\Permission\Models\Role::where('name', 'Student')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'Student']);
        }

        // Create Permissions
        $permissions = [
            'store.receipts',
            'manage.subscriptions',
            'edit.subscriptions',
            'view.payments',
            'manage.payments',
            'delete.receipts' // needed for void
        ];
        
        foreach ($permissions as $perm) {
            if (!\Spatie\Permission\Models\Permission::where('name', $perm)->exists()) {
                \Spatie\Permission\Models\Permission::create(['name' => $perm]);
            }
        }

        // Setup Admin
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->admin->givePermissionTo($permissions);

        // Setup Student and Plan
        $this->student = User::factory()->create();
        $this->student->assignRole('Student');

        $this->course = Course::factory()->create();
        $this->plan = SubscriptionPlan::create([
            'course_id' => $this->course->id,
            'name' => 'Monthly Plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'plan_type' => 'recurring',
            'is_free' => false,
            'is_active' => true,
        ]);
    }

    public function test_subscription_created_as_pending_if_payment_pending()
    {
        // 1. Create Pending Payment
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card'
        ]);

        // 2. Create Subscription linked to pending payment
        // Using Service directly to test logic
        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->createSubscription($this->student, $this->plan, $payment);

        // 3. Verify status is pending
        $this->assertEquals(UserSubscription::STATUS_PENDING, $subscription->status);
    }

    public function test_payment_completion_activates_subscription()
    {
        // 1. Create Pending Subscription
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->createSubscription($this->student, $this->plan, $payment);

        $this->assertEquals(UserSubscription::STATUS_PENDING, $subscription->status);

        // 2. Update Payment to Completed
        $payment->update(['status' => 'completed']);

        // 3. Verify Subscription activated
        $this->assertEquals(UserSubscription::STATUS_ACTIVE, $subscription->fresh()->status);
    }

    public function test_payment_failure_suspends_subscription()
    {
        // 1. Create Active Subscription
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->createSubscription($this->student, $this->plan, $payment);

        $this->assertEquals(UserSubscription::STATUS_ACTIVE, $subscription->status);

        // 2. Update Payment to Failed
        $payment->update(['status' => 'failed']);

        // 3. Verify Subscription failed/suspended
        $this->assertEquals(UserSubscription::STATUS_FAILED, $subscription->fresh()->status);
        $this->assertFalse((bool)$subscription->fresh()->auto_renew);
    }

    public function test_payment_refund_cancels_subscription()
    {
        // 1. Create Active Subscription
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->createSubscription($this->student, $this->plan, $payment);

        // 2. Update Payment to Refunded
        $payment->update(['status' => 'refunded']);

        // 3. Verify Subscription canceled
        $this->assertEquals(UserSubscription::STATUS_CANCELED, $subscription->fresh()->status);
        $this->assertEquals('Payment Refunded', $subscription->fresh()->cancellation_reason);
    }

    public function test_admin_cannot_activate_subscription_with_pending_payment()
    {
        // 1. Create Pending Subscription
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\SubscriptionService::class);
        $subscription = $service->createSubscription($this->student, $this->plan, $payment);

        // 2. Attempt to update status to active via API
        $response = $this->actingAs($this->admin)->putJson("/api/admin/user-subscriptions/{$subscription->id}", [
            'status' => 'active',
        ]);

        // 3. Verify Validation Error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
        $this->assertEquals(UserSubscription::STATUS_PENDING, $subscription->fresh()->status);
    }

    public function test_receipt_controller_methods_do_not_crash()
    {
        $this->withoutExceptionHandling();
        
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'card'
        ]);

        $receipt = Receipt::create([
            'user_id' => $this->student->id,
            'payment_id' => $payment->id,
            'receipt_number' => 'REC-123',
            'amount' => 10,
            'currency' => 'USD',
            'item_type' => 'subscription_plan',
            'item_id' => $this->plan->id,
            'item_name' => 'Test Plan'
        ]);

        // Test void
        // Route is POST
        $responseVoid = $this->actingAs($this->admin)->postJson("/api/admin/receipts/{$receipt->id}/void", [
            'reason' => 'Test Void'
        ]);
        // Should be 200 or 501, not 500
        $this->assertNotEquals(500, $responseVoid->status());

        // Test download
        $responseDownload = $this->actingAs($this->admin)->getJson("/api/admin/receipts/{$receipt->id}/download");
        $this->assertNotEquals(500, $responseDownload->status());
        
        // Test resend
        $responseResend = $this->actingAs($this->admin)->postJson("/api/admin/receipts/{$receipt->id}/resend");
        $this->assertNotEquals(500, $responseResend->status());
        
        // Test regeneratePdf
        $responseRegenerate = $this->actingAs($this->admin)->postJson("/api/admin/receipts/{$receipt->id}/regenerate-pdf");
        $this->assertNotEquals(500, $responseRegenerate->status());
        
        // Test statistics
        $responseStats = $this->actingAs($this->admin)->getJson("/api/admin/receipts/statistics");
        $this->assertNotEquals(500, $responseStats->status());
    }
}
