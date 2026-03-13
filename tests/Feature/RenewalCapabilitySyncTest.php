<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserFeature;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RenewalCapabilitySyncTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $course;
    protected $plan;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student']);
        $this->user = User::factory()->create();
        $this->course = Course::factory()->create(['status' => 'published']);
        $this->service = app(EntitlementService::class);

        // 1. Create Initial Plan with Feature A
        $this->plan = BillingPlan::create([
            'name' => 'Gold Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'access_type' => 'while_active',
            'is_active' => true,
        ]);
        $this->plan->courses()->attach($this->course->id);

        $featureA = Feature::create(['code' => 'feature.a', 'description' => 'Old Feature']);
        PlanFeature::create([
            'billing_plan_id' => $this->plan->id,
            'feature_id' => $featureA->id,
            'scope_type' => 'App\Models\Course',
            'scope_id' => $this->course->id,
            'value' => '1',
        ]);
    }

    public function test_renewal_should_update_capabilities_to_match_current_plan()
    {
        // 1. Grant Entitlement (User gets Feature A)
        $entitlement = $this->service->grantEntitlement($this->user, $this->plan);

        $this->assertTrue($this->user->hasCapability('feature.a', 'App\Models\Course', $this->course->id));
        $this->assertFalse($this->user->hasCapability('feature.b', 'App\Models\Course', $this->course->id));

        // 2. Modify Plan: Remove Feature A, Add Feature B
        PlanFeature::where('billing_plan_id', $this->plan->id)->delete(); // Remove A

        $featureB = Feature::create(['code' => 'feature.b', 'description' => 'New Feature']);
        PlanFeature::create([
            'billing_plan_id' => $this->plan->id,
            'feature_id' => $featureB->id,
            'scope_type' => 'App\Models\Course',
            'scope_id' => $this->course->id,
            'value' => '1',
        ]);

        // 3. Renew Entitlement
        $this->service->renewEntitlement($entitlement, $this->plan);

        // Refresh the user relation completely
        $this->user->refresh();

        // 4. Verify Capabilities Updated
        // Feature A should be GONE (or at least B should be present).
        // If we want strictly "match current plan", A should be removed.
        // If we want "additive", A might stay.
        // Usually, a renewal implies "I am buying the plan AS IT IS NOW".
        // So strict sync is preferred: User should have what the plan currently offers.

        // Reload user capabilities
        // Note: hasCapability might be cached or loaded from relation?
        // Let's force load capabilities
        $hasA = $this->user->hasCapability('feature.a', 'App\Models\Course', $this->course->id);
        $hasB = $this->user->hasCapability('feature.b', 'App\Models\Course', $this->course->id);

        // Debug output
        // dump($this->user->features()->pluck('feature_code')->toArray());

        $this->assertFalse($hasA, 'Old Feature A should be removed upon renewal');
        $this->assertTrue($hasB, 'New Feature B should be added upon renewal');
    }
}
