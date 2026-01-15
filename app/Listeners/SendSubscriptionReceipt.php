<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Mail\SubscriptionReceiptMail;
use App\Models\Receipt;
use App\Services\ReceiptPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionReceipt implements ShouldQueue
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
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription;

        // Ensure we have the latest data
        $subscription->load(['user', 'payment', 'payment.receipt']);

        // Check if there is a payment and receipt
        if (!$subscription->payment) {
            Log::info("No payment found for subscription ID {$subscription->id}. Skipping receipt email.");
            return;
        }

        // Find the receipt linked to this payment and subscription plan
        $receipt = Receipt::where('payment_id', $subscription->payment_id)
            ->where('item_id', $subscription->subscription_plan_id)
            ->where('item_type', 'subscription_plan')
            ->first();

        if (!$receipt) {
            Log::warning("No receipt found for subscription ID {$subscription->id} (Payment ID: {$subscription->payment_id}). Skipping receipt email.");
            return;
        }

        try {
            // Generate PDF
            $pdf = $this->receiptPdfService->generate($receipt);
            $pdfContent = $pdf->output();

            // Send Email
            Mail::to($subscription->user->email)->send(
                new SubscriptionReceiptMail($receipt, $pdfContent)
            );

            Log::info("Receipt email sent to {$subscription->user->email} for Subscription ID {$subscription->id}");

        } catch (\Exception $e) {
            Log::error("Failed to send receipt email for Subscription ID {$subscription->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
