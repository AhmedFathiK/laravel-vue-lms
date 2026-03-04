<?php

namespace App\Console\Commands;

use App\Models\UserEntitlement;
use App\Services\EntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateEntitlementStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entitlements:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update status of expired entitlements and attempt auto-renewal';

    /**
     * Execute the console command.
     */
    public function handle(EntitlementService $entitlementService)
    {
        $this->info('Starting entitlement status update...');

        // 1. Handle Active -> Past Due or Expired
        // If it's recurring and auto-renew is on, move to past_due (waiting for payment)
        // If not recurring or auto-renew is off, move to expired

        $toPastDue = UserEntitlement::where('status', UserEntitlement::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->where('auto_renew', true)
            ->get();

        foreach ($toPastDue as $entitlement) {
            $entitlement->update(['status' => UserEntitlement::STATUS_PAST_DUE]);

            // Attempt auto-renewal immediately
            $this->info("Attempting auto-renewal for entitlement {$entitlement->id}...");
            $success = $entitlementService->attemptAutoRenew($entitlement);

            if ($success) {
                $this->info("Successfully auto-renewed entitlement {$entitlement->id}.");
            } else {
                $this->warn("Auto-renewal failed for entitlement {$entitlement->id}.");
            }
        }

        $toExpired = UserEntitlement::where('status', UserEntitlement::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->where('auto_renew', false)
            ->update(['status' => UserEntitlement::STATUS_EXPIRED]);

        if ($toPastDue->count() > 0) {
            $this->info("Moved {$toPastDue->count()} entitlements to past_due state.");
            Log::info("Moved {$toPastDue->count()} entitlements to past_due state.");
        }
        if ($toExpired > 0) {
            $this->info("Moved {$toExpired} entitlements to expired state.");
            Log::info("Moved {$toExpired} entitlements to expired state.");
        }

        // 2. Handle Past Due -> Failed (Grace Period check)
        $maxGraceDays = config('entitlement.grace_period.max_days', 7);
        $failedCount = 0;

        UserEntitlement::where('status', UserEntitlement::STATUS_PAST_DUE)
            ->whereNotNull('ends_at')
            ->chunkById(100, function ($entitlements) use ($maxGraceDays, &$failedCount, $entitlementService) {
                foreach ($entitlements as $entitlement) {
                    // 2.1 Attempt auto-renewal for past_due entitlements if they have auto_renew on
                    if ($entitlement->auto_renew) {
                        $this->info("Retrying auto-renewal for past_due entitlement {$entitlement->id}...");
                        if ($entitlementService->attemptAutoRenew($entitlement)) {
                            $this->info("Successfully auto-renewed past_due entitlement {$entitlement->id}.");
                            continue; // Skip further processing for this entitlement as it's now active
                        }
                    }

                    // 2.2 Check if grace period has expired (using fixed max_days for simplicity and matching test)
                    if ($entitlement->ends_at->copy()->addDays($maxGraceDays)->isPast()) {
                        $entitlement->update(['status' => UserEntitlement::STATUS_FAILED]);
                        $failedCount++;
                    }
                }
            });

        if ($failedCount > 0) {
            $this->info("Marked {$failedCount} entitlements as failed (Grace period check).");
            Log::info("Marked {$failedCount} entitlements as failed (Grace period check).");
        }

        if ($toPastDue === 0 && $toExpired === 0 && $failedCount === 0) {
            $this->info('No entitlement status updates required.');
        }

        return 0;
    }
}
