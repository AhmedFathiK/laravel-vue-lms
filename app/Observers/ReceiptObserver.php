<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\TrashItem;
use App\Models\UserSubscription;

class ReceiptObserver
{
    /**
     * Handle the Receipt "deleted" event.
     */
    public function deleted(Receipt $receipt): void
    {
        TrashItem::create([
            'model_type' => Receipt::class,
            'model_id' => $receipt->id,
            'name' => $receipt->receipt_number,
            'deleted_at' => now(),
            'additional_data' => [
                'reason' => $receipt->deletion_reason,
                'deleted_by' => $receipt->deleted_by,
            ],
        ]);
    }

    /**
     * Handle the Receipt "restored" event.
     */
    public function restored(Receipt $receipt): void
    {
        // When a receipt is restored, we also restore its payment and potentially its subscription.
        
        // Restore the associated payment
        $receipt->payment()->onlyTrashed()->first()?->restore();

        // Restore the associated subscription, but only if it wasn't deleted separately
        $subscription = $receipt->subscription()->onlyTrashed()->first();
        if ($subscription) {
            $isSubscriptionTrashedSeparately = TrashItem::where('model_type', get_class($subscription))
                ->where('model_id', $subscription->id)
                ->exists();

            if (!$isSubscriptionTrashedSeparately) {
                $subscription->restore();
            }
        }

        // Finally, remove the receipt's own trash item record
        TrashItem::where('model_type', Receipt::class)
            ->where('model_id', $receipt->id)
            ->delete();
    }

    /**
     * Handle the Receipt "force deleted" event.
     */
    public function forceDeleted(Receipt $receipt): void
    {
        // Delete the related TrashItem record
        TrashItem::where('model_type', Receipt::class)
            ->where('model_id', $receipt->id)
            ->delete();

        // Retrieve and force delete the related payment
        $payment = Payment::withTrashed()->find($receipt->payment_id);
        if ($payment) {
            $payment->forceDelete();
        }

        // Retrieve and force delete the related user subscription
        $subscription = UserSubscription::withTrashed()->where('payment_id', $receipt->payment_id)->first();
        if ($subscription) {
            $subscription->forceDelete();
        }
    }
}