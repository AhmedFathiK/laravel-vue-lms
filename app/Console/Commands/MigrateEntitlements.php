<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserEntitlement;
use App\Models\UserCapability;
use App\Models\BillingPlan;
use Illuminate\Support\Facades\DB;

class MigrateEntitlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lms:migrate-entitlements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate and refresh UserCapabilities for all active entitlements based on their Billing Plans.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting entitlement migration...');

        $entitlements = UserEntitlement::with('billingPlan.planFeatures.feature')->get();

        $bar = $this->output->createProgressBar($entitlements->count());
        $bar->start();

        foreach ($entitlements as $entitlement) {
            $plan = $entitlement->billingPlan;

            if (!$plan) {
                // If plan is deleted, we might skip or log it.
                // For now, skipping.
                $bar->advance();
                continue;
            }

            DB::transaction(function () use ($entitlement, $plan) {
                // We will refresh features.
                // First, get existing features to potentially avoid churn if already correct,
                // but deleting and recreating is safer and cleaner for "migration".
                // To preserve history or IDs, we could update, but features are essentially value objects here.

                // Delete existing features
                $entitlement->features()->delete();

                // Create new features based on PlanFeatures
                foreach ($plan->planFeatures as $pf) {
                    if (!$pf->feature) continue;

                    $entitlement->features()->create([
                        'feature_code' => $pf->feature->code,
                        'scope_type' => $pf->scope_type,
                        'scope_id' => $pf->scope_id,
                        'value' => $pf->value,
                    ]);
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Entitlement migration completed successfully.');
    }
}
