<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserEntitlement;
use App\Models\UserCapability;
use App\Models\PlanFeature;

class EntitlementCapabilityBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entitlements = UserEntitlement::with('billingPlan.planFeatures.feature')->get();

        foreach ($entitlements as $entitlement) {
            $plan = $entitlement->billingPlan;
            if (!$plan) continue;

            foreach ($plan->planFeatures as $pf) {
                if (!$pf->feature) continue;

                UserCapability::firstOrCreate([
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
