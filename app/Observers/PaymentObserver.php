<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\UserSubscription;

use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        Log::info('PaymentObserver updated: ' . $payment->id . ' status: ' . $payment->status);
        if ($payment->isDirty('status')) {
            $newStatus = $payment->status;
            $originalStatus = $payment->getOriginal('status');
            Log::info("Payment status changed from {$originalStatus} to {$newStatus}");
            
            // Find linked subscription
            // Payment hasOne UserSubscription
            $subscription = $payment->subscription;
            
            if ($subscription) {
                Log::info('Linked subscription found: ' . $subscription->id . ' status: ' . $subscription->status);
                // Case: pending -> completed => Activate
                if ($newStatus === 'completed') {
                    if ($subscription->status === UserSubscription::STATUS_PENDING) {
                        Log::info('Activating subscription');
                        $subscription->update([
                            'status' => UserSubscription::STATUS_ACTIVE,
                        ]);
                    }
                }
                
                // Case: completed -> failed => Suspend/Fail
                if ($newStatus === 'failed') {
                    if ($subscription->status === UserSubscription::STATUS_ACTIVE) {
                        Log::info('Failing subscription');
                        $subscription->update([
                            'status' => UserSubscription::STATUS_FAILED,
                            'auto_renew' => false,
                        ]);
                    }
                }
                
                // Case: completed -> refunded => Cancel
                if ($newStatus === 'refunded') {
                    Log::info('Canceling subscription (refunded)');
                    $subscription->update([
                        'status' => UserSubscription::STATUS_CANCELED,
                        'cancellation_reason' => 'Payment Refunded',
                        'auto_renew' => false,
                    ]);
                }
            } else {
                Log::info('No linked subscription found for payment ' . $payment->id);
            }
        }
    }
}
