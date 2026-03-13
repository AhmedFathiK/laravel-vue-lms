<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_available_plans_returns_all_features()
    {
        if (!Role::where('name', 'Student')->exists()) {
            Role::create(['name' => 'Student']);
        }
        $user = User::factory()->create();
        $course = Course::factory()->create(['status' => 'published']);

        $plan = BillingPlan::create([
            'name' => 'Monthly Plan',
            'price' => 100,
            'currency' => 'USD',
            'billing_type' => 'recurring',
            'billing_interval' => 'month',
            'access_type' => 'while_active',
            'is_active' => true,
        ]);
        $plan->courses()->attach($course->id);

        $feature = Feature::create(['code' => 'test.feature', 'description' => 'Test Feature']);
        PlanFeature::create([
            'billing_plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'scope_type' => Course::class,
            'scope_id' => $course->id,
            'value' => '1',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/learner/courses/{$course->id}/billing-plans");

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'plans',
            'activeEntitlement',
            'allFeatures'
        ]);

        // Check that allFeatures is populated
        $allFeatures = $response->json('allFeatures');
        $this->assertNotEmpty($allFeatures, 'allFeatures should not be empty');

        // Check that plans have features
        $plans = $response->json('plans');
        $this->assertNotEmpty($plans, 'plans should not be empty');
        $this->assertArrayHasKey('features', $plans[0], 'plan should have features key');
        $this->assertNotEmpty($plans[0]['features'], 'plan features should not be empty');
    }
}
