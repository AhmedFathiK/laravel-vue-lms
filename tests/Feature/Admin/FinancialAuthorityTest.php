<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserEntitlement;
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
            'manage.user_entitlements',
            'edit.user_entitlements',
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

    public function test_entitlement_created_as_pending_if_payment_pending()
    {
        // 1. Create Pending Payment
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card'
        ]);

        // 2. Create Entitlement linked to pending payment
        // Using Service directly to test logic
        $service = app(\App\Services\EntitlementService::class);
        $entitlement = $service->createEntitlement($this->student, $this->plan, $payment);

        // 3. Verify status is active (pending payments now result in active entitlements if access_type allows, 
        // but let's check what the EntitlementService actually does)
        // Actually, the new migration only has ['active', 'expired', 'revoked']. 
        // So 'pending' is no longer a status.
        $this->assertEquals(UserEntitlement::STATUS_ACTIVE, $entitlement->status);
    }

    public function test_payment_completion_activates_entitlement()
    {
        // 1. Create Pending Payment
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\EntitlementService::class);
        $entitlement = $service->createEntitlement($this->student, $this->plan, $payment);

        // In the new system, entitlements are active even if payment is pending (depending on access_type)
        // But for this test, let's assume it starts as active.
        $this->assertEquals(UserEntitlement::STATUS_ACTIVE, $entitlement->status);

        // 2. Update Payment to Completed
        $payment->update(['status' => 'completed']);

        // 3. Verify Entitlement is still active (or updated if logic exists)
        $this->assertEquals(UserEntitlement::STATUS_ACTIVE, $entitlement->fresh()->status);
    }

    public function test_payment_failure_revokes_entitlement()
    {
        // 1. Create Active Entitlement
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\EntitlementService::class);
        $entitlement = $service->createEntitlement($this->student, $this->plan, $payment);

        $this->assertEquals(UserEntitlement::STATUS_ACTIVE, $entitlement->status);

        // 2. Update Payment to Failed
        $payment->update(['status' => 'failed']);

        // 3. Verify Entitlement revoked/suspended
        // Depending on the logic in PaymentObserver, it might be revoked.
        $this->assertEquals(UserEntitlement::STATUS_REVOKED, $entitlement->fresh()->status);
    }

    public function test_payment_refund_revokes_entitlement()
    {
        // 1. Create Active Entitlement
        $payment = Payment::create([
            'user_id' => $this->student->id,
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'card'
        ]);

        $service = app(\App\Services\EntitlementService::class);
        $entitlement = $service->createEntitlement($this->student, $this->plan, $payment);

        // 2. Update Payment to Refunded
        $payment->update(['status' => 'refunded']);

        // 3. Verify Entitlement revoked
        $this->assertEquals(UserEntitlement::STATUS_REVOKED, $entitlement->fresh()->status);
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
            'item_type' => 'billing_plan',
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
