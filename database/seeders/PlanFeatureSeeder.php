<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BillingPlan;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Course;

class PlanFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = BillingPlan::with('courses')->get();
        $features = Feature::whereIn('code', [
            'revision.access',
            'content.free.access',
            'content.paid.access',
            'placement_test.access'
        ])->get();

        foreach ($plans as $plan) {
            foreach ($plan->courses as $course) {
                foreach ($features as $feature) {
                    PlanFeature::firstOrCreate([
                        'billing_plan_id' => $plan->id,
                        'feature_id' => $feature->id,
                        'scope_type' => 'App\Models\Course',
                        'scope_id' => $course->id,
                    ]);
                }
            }
        }
    }
}
