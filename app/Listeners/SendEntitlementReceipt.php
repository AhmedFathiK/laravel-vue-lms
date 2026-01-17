<?php

namespace App\Listeners;

use App\Events\EntitlementCreated;
use App\Mail\EntitlementReceiptMail;
use App\Models\Receipt;
use App\Services\ReceiptPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEntitlementReceipt implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected ReceiptPdfService $receiptPdfService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EntitlementCreated $event): void
    {
        $entitlement = $event->entitlement;

        // Ensure we have the latest data
        $entitlement->load(['user', 'payment', 'payment.receipt']);

        // Check if there is a payment and receipt
        if (!$entitlement->payment) {
            Log::info("No payment found for entitlement ID {$entitlement->id}. Skipping receipt email.");
            return;
        }

        // Find the receipt linked to this payment and billing plan
        $receipt = Receipt::where('payment_id', $entitlement->payment_id)
            ->where('item_id', $entitlement->billing_plan_id)
            ->where('item_type', 'billing_plan')
            ->first();

        if (!$receipt) {
            Log::warning("No receipt found for entitlement ID {$entitlement->id} (Payment ID: {$entitlement->payment_id}). Skipping receipt email.");
            return;
        }

        try {
            // Generate PDF
            $pdf = $this->receiptPdfService->generate($receipt);
            $pdfContent = $pdf->output();

            // Send Email
            Mail::to($entitlement->user->email)->send(
                new EntitlementReceiptMail($receipt, $pdfContent)
            );

            Log::info("Receipt email sent to {$entitlement->user->email} for Entitlement ID {$entitlement->id}");

        } catch (\Exception $e) {
            Log::error("Failed to send receipt email for Entitlement ID {$entitlement->id}: " . $e->getMessage());
            // Do not re-throw to prevent rolling back the transaction or failing the request
            // throw $e;
        }
    }
}
