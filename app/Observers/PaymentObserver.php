<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\UserEntitlement;

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
            
            // Find linked entitlement
            // Payment hasOne UserEntitlement
            $entitlement = $payment->entitlement;
            
            if ($entitlement) {
                Log::info('Linked entitlement found: ' . $entitlement->id . ' status: ' . $entitlement->status);
                // Case: pending -> completed => Activate
                if ($newStatus === 'completed') {
                    if ($entitlement->status === UserEntitlement::STATUS_PENDING) {
                        Log::info('Activating entitlement');
                        $entitlement->update([
                            'status' => UserEntitlement::STATUS_ACTIVE,
                        ]);
                    }
                }
                
                // Case: completed -> failed => Suspend/Fail
                if ($newStatus === 'failed') {
                    if ($entitlement->status === UserEntitlement::STATUS_ACTIVE) {
                        Log::info('Failing entitlement');
                        $entitlement->update([
                            'status' => UserEntitlement::STATUS_FAILED,
                        ]);
                    }
                }
                
                // Case: completed -> refunded => Cancel
                if ($newStatus === 'refunded') {
                    Log::info('Canceling entitlement (refunded/voided)');
                    $entitlement->update([
                        'status' => UserEntitlement::STATUS_CANCELED,
                    ]);
                }
            } else {
                Log::info('No linked entitlement found for payment ' . $payment->id);
            }
        }
    }
}
