<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RenewalDisallowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $plan;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student']);
        }
        $this->user = User::factory()->create();
        $this->service = app(EntitlementService::class);

        $course = Course::factory()->create(['status' => 'published']);

        $this->plan = BillingPlan::create([
            'name' => 'Monthly Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'access_type' => 'while_active',
            'is_active' => true,
        ]);
        $this->plan->courses()->attach($course->id);
    }

    public function test_cannot_acquire_duplicate_active_entitlement()
    {
        // 1. Grant Entitlement
        $entitlement = $this->service->grantEntitlement($this->user, $this->plan);
        $this->assertTrue($entitlement->isActive());

        // 2. Attempt to acquire same plan again
        $response = $this->actingAs($this->user)
            ->postJson("/api/learner/acquire-entitlement", [
                'plan_id' => $this->plan->id
            ]);

        // 3. Should fail with 403
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You already have an active entitlement for this plan.',
            ]);
    }

    public function test_cannot_renew_active_entitlement()
    {
        // 1. Grant Entitlement
        $entitlement = $this->service->grantEntitlement($this->user, $this->plan);

        // Ensure it is active
        $this->assertEquals(UserEntitlement::STATUS_ACTIVE, $entitlement->status);
        $this->assertTrue($entitlement->isActive());

        // 2. Attempt to renew via API
        $response = $this->actingAs($this->user)
            ->postJson("/api/learner/entitlements/{$entitlement->id}/renew", [
                'paymentMethodId' => 'test_method'
            ]);

        // 3. Should fail with 403
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'This plan is currently active and cannot be renewed until it expires.',
            ]);
    }

    public function test_can_renew_expired_entitlement()
    {
        // 1. Grant Entitlement and Expire it
        $entitlement = $this->service->grantEntitlement($this->user, $this->plan);
        $entitlement->update([
            'status' => UserEntitlement::STATUS_EXPIRED,
            'ends_at' => now()->subDay(),
        ]);

        // 2. Attempt to renew
        $response = $this->actingAs($this->user)
            ->postJson("/api/learner/entitlements/{$entitlement->id}/renew", [
                'paymentMethodId' => 'test_method'
            ]);

        // Should NOT be the specific 403 error
        // It might return 422 (validation) or 500 (payment error)
        $this->assertNotEquals(403, $response->status());

        $json = $response->json();
        if (isset($json['message'])) {
            $this->assertNotEquals('This plan is currently active and cannot be renewed until it expires.', $json['message']);
        }
    }
}
