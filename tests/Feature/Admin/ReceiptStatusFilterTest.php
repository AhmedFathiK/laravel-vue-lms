<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Receipt;
use App\Models\Payment;
use App\Models\BillingPlan;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ReceiptStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permissions
        if (!Permission::where('name', 'view.receipts')->exists()) {
            Permission::create(['name' => 'view.receipts', 'guard_name' => 'web']);
        }
        if (!Role::where('name', 'admin')->exists()) {
            $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        } else {
            $role = Role::findByName('admin', 'web');
        }
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student', 'guard_name' => 'web']);
        }

        $role->givePermissionTo('view.receipts');
    }

    public function test_backend_handles_unknown_status_gracefully()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Create some receipts
        $plan = BillingPlan::factory()->create();

        // 1. Completed Receipt
        $payment1 = Payment::create([
            'user_id' => $admin->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'manual',
            'payment_provider' => 'Manual',
            'transaction_id' => 'tx_1',
        ]);
        $receipt1 = Receipt::create([
            'user_id' => $admin->id,
            'payment_id' => $payment1->id,
            'receipt_number' => 'REC-001',
            'item_type' => 'billing_plan',
            'item_id' => $plan->id,
            'item_name' => 'Plan 1',
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // 2. Refunded Receipt
        $payment2 = Payment::create([
            'user_id' => $admin->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'refunded',
            'payment_method' => 'manual',
            'payment_provider' => 'Manual',
            'transaction_id' => 'tx_2',
        ]);
        $receipt2 = Receipt::create([
            'user_id' => $admin->id,
            'payment_id' => $payment2->id,
            'receipt_number' => 'REC-002',
            'item_type' => 'billing_plan',
            'item_id' => $plan->id,
            'item_name' => 'Plan 1',
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // Request with unknown status
        $response = $this->actingAs($admin)->getJson('/api/admin/receipts?status=some_unknown_status');

        // Should return 200 OK
        $response->assertStatus(200);

        // Should return all receipts (default behavior for unknown filter)
        // Note: Assuming no other filters are active.
        $data = $response->json('items'); // ReceiptController returns 'items' key in custom response

        // If 'items' is not present, check structure (Resource collection usually returns 'data')
        // The controller returns:
        // return response()->json([
        //    'items' => ReceiptResource::collection($receipts->items()),
        //    ...
        // ]);

        $ids = collect($data)->pluck('id');
        $this->assertContains($receipt1->id, $ids);
        $this->assertContains($receipt2->id, $ids);
    }

    public function test_backend_filters_known_statuses_correctly()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = BillingPlan::factory()->create();

        // 1. Completed Receipt
        $payment1 = Payment::create([
            'user_id' => $admin->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => 'manual',
            'payment_provider' => 'Manual',
            'transaction_id' => 'tx_1',
        ]);
        $receipt1 = Receipt::create([
            'user_id' => $admin->id,
            'payment_id' => $payment1->id,
            'receipt_number' => 'REC-001',
            'item_type' => 'billing_plan',
            'item_id' => $plan->id,
            'item_name' => 'Plan 1',
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // 2. Refunded Receipt
        $payment2 = Payment::create([
            'user_id' => $admin->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'refunded',
            'payment_method' => 'manual',
            'payment_provider' => 'Manual',
            'transaction_id' => 'tx_2',
        ]);
        $receipt2 = Receipt::create([
            'user_id' => $admin->id,
            'payment_id' => $payment2->id,
            'receipt_number' => 'REC-002',
            'item_type' => 'billing_plan',
            'item_id' => $plan->id,
            'item_name' => 'Plan 1',
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // Test Completed
        $response = $this->actingAs($admin)->getJson('/api/admin/receipts?status=completed');
        $response->assertStatus(200);
        $ids = collect($response->json('items'))->pluck('id');
        $this->assertContains($receipt1->id, $ids);
        $this->assertNotContains($receipt2->id, $ids);

        // Test Refunded
        $response = $this->actingAs($admin)->getJson('/api/admin/receipts?status=refunded');
        $response->assertStatus(200);
        $ids = collect($response->json('items'))->pluck('id');
        $this->assertNotContains($receipt1->id, $ids);
        $this->assertContains($receipt2->id, $ids);
    }
}
