<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserFeature;
use App\Models\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RevisionSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @var User */
    protected $user;
    /** @var Course */
    protected $course;
    /** @var BillingPlan */
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Student']);
        $this->user = User::factory()->create();
        $this->course = Course::factory()->create(['status' => 'published']);

        // Seed Revision Feature
        Feature::firstOrCreate(['code' => 'revision.access'], ['description' => 'Access Revision']);
    }

    public function test_user_without_revision_feature_cannot_access_revision_api()
    {
        // Grant Basic Entitlement WITHOUT revision feature
        $plan = BillingPlan::create([
            'name' => 'Basic Plan',
            'price' => 0,
            'currency' => 'USD',
            'access_type' => 'lifetime',
            'billing_type' => 'one_time',
            'is_active' => true
        ]);
        $plan->courses()->attach($this->course->id);

        UserEntitlement::create([
            'user_id' => $this->user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Set active course
        $this->user->active_course_id = $this->course->id;
        $this->user->save();

        // Attempt to access revision API
        $response = $this->actingAs($this->user)
            ->getJson("/api/revision/statistics");

        // Assuming route for index is GET /api/revision/items or similar.
        // Let's use getGrammarTopics which we definitely secured.
        $response = $this->actingAs($this->user)
            ->getJson("/api/revision/grammar-topics");

        $response->assertStatus(403);
    }

    public function test_user_with_revision_feature_can_access_revision_api()
    {
        // Grant Entitlement WITH revision feature
        $plan = BillingPlan::create([
            'name' => 'Pro Plan',
            'price' => 10,
            'currency' => 'USD',
            'access_type' => 'lifetime',
            'billing_type' => 'one_time',
            'is_active' => true
        ]);
        $plan->courses()->attach($this->course->id);

        $entitlement = UserEntitlement::create([
            'user_id' => $this->user->id,
            'billing_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        UserFeature::create([
            'user_entitlement_id' => $entitlement->id,
            'feature_code' => 'revision.access',
            'scope_type' => 'App\Models\Course',
            'scope_id' => $this->course->id,
            'value' => '1',
        ]);

        // Set active course
        $this->user->active_course_id = $this->course->id;
        $this->user->save();

        // Attempt to access revision API
        $response = $this->actingAs($this->user)
            ->getJson("/api/revision/grammar-topics");

        $response->assertStatus(200);
    }
}
