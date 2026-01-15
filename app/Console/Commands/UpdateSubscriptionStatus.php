<?php

namespace App\Console\Commands;

use App\Models\UserSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateSubscriptionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update status of expired subscriptions to expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting subscription status update...');

        // 1. Handle Active -> Past Due (Auto-renew enabled, term ended)
        $pastDueCount = UserSubscription::where('status', UserSubscription::STATUS_ACTIVE)
            ->where('auto_renew', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => UserSubscription::STATUS_PAST_DUE]);

        if ($pastDueCount > 0) {
            $this->info("Moved {$pastDueCount} subscriptions to past_due state.");
            Log::info("Moved {$pastDueCount} subscriptions to past_due state.");
        }

        // 2. Handle Active -> Expired (Auto-renew disabled, term ended)
        $expiredCount = UserSubscription::where('status', UserSubscription::STATUS_ACTIVE)
            ->where('auto_renew', false)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => UserSubscription::STATUS_EXPIRED]);

        if ($expiredCount > 0) {
            $this->info("Expired {$expiredCount} subscriptions.");
            Log::info("Expired {$expiredCount} subscriptions via scheduled command.");
        }

        // 3. Handle Past Due -> Failed (Configurable Grace Period)
        // Grace period is calculated as: min(duration * percentage, max_days)
        $gracePercentage = config('subscription.grace_period.percentage', 10);
        $maxGraceDays = config('subscription.grace_period.max_days', 7);
        $failedCount = 0;

        UserSubscription::where('status', UserSubscription::STATUS_PAST_DUE)
            ->whereNotNull('ends_at')
            ->whereNotNull('starts_at')
            ->chunkById(100, function ($subscriptions) use ($gracePercentage, $maxGraceDays, &$failedCount) {
                foreach ($subscriptions as $subscription) {
                    $durationInDays = $subscription->starts_at->diffInDays($subscription->ends_at);
                    
                    // Calculate grace days based on percentage
                    $calculatedGraceDays = round($durationInDays * ($gracePercentage / 100));
                    
                    // Cap at max grace days
                    $graceDays = min($calculatedGraceDays, $maxGraceDays);
                    
                    // Check if grace period has expired
                    // The subscription fails if current time is past (ends_at + graceDays)
                    if ($subscription->ends_at->copy()->addDays($graceDays)->isPast()) {
                        $subscription->update(['status' => UserSubscription::STATUS_FAILED]);
                        $failedCount++;
                    }
                }
            });

        if ($failedCount > 0) {
            $this->info("Moved {$failedCount} past_due subscriptions to failed state (Grace period check).");
            Log::info("Moved {$failedCount} past_due subscriptions to failed state (Grace period check).");
        }

        if ($pastDueCount === 0 && $expiredCount === 0 && $failedCount === 0) {
            $this->info('No subscription status updates required.');
        }
        
        return 0;
    }
}
