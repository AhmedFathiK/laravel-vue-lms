<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionSafetyTest extends TestCase
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
        
        // Setup Roles first
        \Spatie\Permission\Models\Role::create(['name' => 'Admin']);
        \Spatie\Permission\Models\Role::create(['name' => 'Student']);

        // Create Permissions
        \Spatie\Permission\Models\Permission::create(['name' => 'store.receipts']);
        \Spatie\Permission\Models\Permission::create(['name' => 'manage.subscriptions']);
        \Spatie\Permission\Models\Permission::create(['name' => 'edit.subscriptions']);
        \Spatie\Permission\Models\Permission::create(['name' => 'view.payments']);

        // Setup Admin with permissions
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->admin->givePermissionTo([
            'store.receipts', 
            'manage.subscriptions', 
            'edit.subscriptions',
            'view.payments'
        ]);

        // Setup Student and Plan
        $this->student = User::factory()->create();
        // UserFactory automatically assigns 'Student', so we don't need to do it again manually
        // But we needed to ensure the Role existed first (done above)

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

    public function test_receipt_creation_automatically_creates_subscription()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/receipts', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'plan_id' => $this->plan->id,
            'amount' => 10.00,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'notes' => 'Test receipt',
        ]);

        $response->assertStatus(201);

        // Verify Subscription exists
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $this->student->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    public function test_cannot_modify_payment_of_active_subscription()
    {
        // 1. Create Subscription with Payment
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'manual'
        ]);

        $subscription = UserSubscription::create([
            'user_id' => $this->student->id,
            'subscription_plan_id' => $this->plan->id,
            'payment_id' => $payment->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // 2. Attempt to remove payment (set to null)
        $response = $this->actingAs($this->admin)->putJson("/api/admin/user-subscriptions/{$subscription->id}", [
            'payment_id' => null, 
        ]);

        // Note: The controller/request validation now prevents this modification
        // We expect a 422 Unprocessable Entity
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['paymentId']);
        
        // 3. Attempt to change payment to another ID
        $otherPayment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 20, 
            'currency' => 'USD', 
            'status' => 'completed',
            'payment_method' => 'manual'
        ]);

        $response2 = $this->actingAs($this->admin)->putJson("/api/admin/user-subscriptions/{$subscription->id}", [
            'payment_id' => $otherPayment->id,
        ]);

        $response2->assertStatus(422);
    }

    public function test_cannot_delete_payment_linked_to_subscription()
    {
        // 1. Create Linked Payment and Subscription
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending', // Even pending payments shouldn't be deleted if linked
            'payment_method' => 'manual'
        ]);

        $subscription = UserSubscription::create([
            'user_id' => $this->student->id,
            'subscription_plan_id' => $this->plan->id,
            'payment_id' => $payment->id,
            'status' => 'past_due', // Even past_due shouldn't allow payment deletion
            'starts_at' => now()->subMonth(),
        ]);

        // 2. Attempt to delete payment
        $response = $this->actingAs($this->admin)->deleteJson("/api/admin/payments/{$payment->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_automatic_subscription_rollback_on_failure()
    {
        // Mock a failure by forcing an invalid state or exception if possible
        // For this test, we'll simulate a duplicate subscription exception which is handled by the controller
        
        // 1. Create an existing active subscription
        UserSubscription::create([
            'user_id' => $this->student->id,
            'subscription_plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // 2. Attempt to create a receipt for the same plan (should trigger DuplicateSubscriptionException)
        // Note: The controller catches this exception and returns 422, which is what we expect
        $response = $this->actingAs($this->admin)->postJson('/api/admin/receipts', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'plan_id' => $this->plan->id,
            'amount' => 10.00,
            'currency' => 'USD',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'notes' => 'Test receipt',
        ]);

        // 3. Verify Rollback: No new receipt should be created
        // The DuplicateSubscriptionException is caught and converted to a 422 response in the controller
        $response->assertStatus(422); 
        $response->assertJson(['message' => 'Failed to link subscription: User already has an active subscription.']);
        
        // Ensure no *new* receipt was created (assuming 0 receipts before)
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
