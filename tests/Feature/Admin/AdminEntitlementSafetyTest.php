<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Payment;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEntitlementSafetyTest extends TestCase
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
        \Spatie\Permission\Models\Permission::create(['name' => 'manage.user_entitlements']);
        \Spatie\Permission\Models\Permission::create(['name' => 'edit.user_entitlements']);
        \Spatie\Permission\Models\Permission::create(['name' => 'view.payments']);

        // Setup Admin with permissions
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->admin->givePermissionTo([
            'store.receipts', 
            'manage.user_entitlements', 
            'edit.user_entitlements',
            'view.payments'
        ]);

        // Setup Student and Plan
        $this->student = User::factory()->create();
        // UserFactory automatically assigns 'Student', so we don't need to do it again manually
        // But we needed to ensure the Role existed first (done above)

        $this->course = Course::factory()->create();
        $this->plan = BillingPlan::create([
            'name' => 'Monthly Plan',
            'price' => 10.00,
            'currency' => 'USD',
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'access_type' => 'while_active',
            'is_active' => true,
        ]);
    }

    public function test_receipt_creation_automatically_creates_entitlement()
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

        // Verify Entitlement exists
        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $this->student->id,
            'billing_plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    public function test_cannot_modify_payment_of_active_entitlement()
    {
        // 1. Create Entitlement with Payment
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'manual'
        ]);

        $entitlement = UserEntitlement::create([
            'user_id' => $this->student->id,
            'billing_plan_id' => $this->plan->id,
            'payment_id' => $payment->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // 2. Attempt to remove payment (set to null)
        $response = $this->actingAs($this->admin)->putJson("/api/admin/user-entitlements/{$entitlement->id}", [
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

        $response2 = $this->actingAs($this->admin)->putJson("/api/admin/user-entitlements/{$entitlement->id}", [
            'payment_id' => $otherPayment->id,
        ]);

        $response2->assertStatus(422);
    }

    public function test_cannot_delete_payment_linked_to_entitlement()
    {
        // 1. Create Linked Payment and Entitlement
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending', // Even pending payments shouldn't be deleted if linked
            'payment_method' => 'manual'
        ]);

        $entitlement = UserEntitlement::create([
            'user_id' => $this->student->id,
            'billing_plan_id' => $this->plan->id,
            'payment_id' => $payment->id,
            'status' => 'active', // Changed from past_due as it's not in migration's enum
            'starts_at' => now()->subMonth(),
        ]);

        // 2. Attempt to delete payment
        $response = $this->actingAs($this->admin)->deleteJson("/api/admin/payments/{$payment->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_automatic_entitlement_rollback_on_failure()
    {
        // Mock a failure by forcing an invalid state or exception if possible
        // For this test, we'll simulate a duplicate entitlement exception which is handled by the controller
        
        // 1. Create an existing active entitlement
        UserEntitlement::create([
            'user_id' => $this->student->id,
            'billing_plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // 2. Attempt to create a receipt for the same plan (should trigger DuplicateEntitlementException)
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
        // The DuplicateEntitlementException is caught and converted to a 422 response in the controller
        $response->assertStatus(422); 
        $response->assertJson(['message' => 'Failed to link entitlement: User already has an active entitlement.']);
        
        // Ensure no *new* receipt was created (assuming 0 receipts before)
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
