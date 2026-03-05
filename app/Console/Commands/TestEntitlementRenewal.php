<?php

namespace App\Console\Commands;

use App\Models\UserEntitlement;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

class TestEntitlementRenewal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entitlements:test-renew {entitlement_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger renewal attempt for an entitlement';

    /**
     * Execute the console command.
     */
    public function handle(EntitlementService $entitlementService)
    {
        $id = $this->argument('entitlement_id');
        $entitlement = UserEntitlement::find($id);

        if (!$entitlement) {
            $this->error("Entitlement with ID {$id} not found.");
            return 1;
        }

        $this->info("Attempting renewal for Entitlement #{$entitlement->id} (User: {$entitlement->user->email})...");
        
        // Force auto_renew to true temporarily for testing if it's off
        $originalAutoRenew = $entitlement->auto_renew;
        if (!$originalAutoRenew) {
            $this->warn("Entitlement auto_renew is currently OFF. Forcing it ON for this test.");
            $entitlement->update(['auto_renew' => true]);
        }

        $success = $entitlementService->attemptAutoRenew($entitlement);

        if ($success) {
            $this->info("Renewal SUCCESSFUL!");
        } else {
            $this->error("Renewal FAILED. Check logs for details.");
        }

        // Restore original state if we changed it
        if (!$originalAutoRenew) {
            $entitlement->update(['auto_renew' => false]);
        }

        return $success ? 0 : 1;
    }
}
