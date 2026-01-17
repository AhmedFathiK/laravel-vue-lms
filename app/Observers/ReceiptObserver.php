<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\TrashItem;
use App\Models\UserEntitlement;

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
        // When a receipt is restored, we also restore its payment and potentially its entitlement.
        
        // Restore the associated payment
        $receipt->payment()->onlyTrashed()->first()?->restore();

        // Restore the associated entitlement, but only if it wasn't deleted separately
        $entitlement = $receipt->entitlement()->onlyTrashed()->first();
        if ($entitlement) {
            $isEntitlementTrashedSeparately = TrashItem::where('model_type', get_class($entitlement))
                ->where('model_id', $entitlement->id)
                ->exists();

            if (!$isEntitlementTrashedSeparately) {
                $entitlement->restore();
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

        // Retrieve and force delete the related user entitlement
        $entitlement = UserEntitlement::withTrashed()->where('payment_id', $receipt->payment_id)->first();
        if ($entitlement) {
            $entitlement->forceDelete();
        }
    }
}