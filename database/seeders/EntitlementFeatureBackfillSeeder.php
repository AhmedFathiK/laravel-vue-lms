<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserEntitlement;
use App\Models\UserFeature;
use App\Models\PlanFeature;

class EntitlementFeatureBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entitlements = UserEntitlement::where('status', 'active')->get();

        foreach ($entitlements as $entitlement) {
            $plan = $entitlement->billingPlan;
            if (!$plan) continue;

            foreach ($plan->planFeatures as $pf) {
                if (!$pf->feature) continue;

                UserFeature::firstOrCreate([
                    'user_entitlement_id' => $entitlement->id,
                    'feature_code' => $pf->feature->code,
                    'scope_type' => $pf->scope_type,
                    'scope_id' => $pf->scope_id,
                ], [
                    'value' => $pf->value,
                ]);
            }
        }
    }
}
