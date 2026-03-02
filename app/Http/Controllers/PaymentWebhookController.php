<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Setting;
use App\Services\EntitlementService;
use App\Services\Payments\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentGateway,
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Verify MyFatoorah Webhook Signature.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('services.myfatoorah.webhook_secret');

        // If secret is not configured, we skip verification (or could fail closed).
        if (empty($secret)) {
            Log::warning('MyFatoorah Webhook: Secret key not configured. Skipping signature verification.');
            return true;
        }

        $signature = $request->header('MyFatoorah-Signature');
        if (empty($signature)) {
            Log::warning('MyFatoorah Webhook: Missing signature header.');
            return false;
        }

        $eventCode = $request->input('Event.Code');
        $data = $request->input('Data', []);

        // Fallback: If Event.Code is missing, try to parse raw content
        // This handles cases where Content-Type header might be missing or incorrect
        if (is_null($eventCode)) {
            $content = $request->getContent();
            $json = json_decode($content, true);
            if (is_array($json)) {
                $request->merge($json); // Merge into request so input() works later
                $eventCode = $request->input('Event.Code');
                $data = $request->input('Data', []);
            } else {
                Log::warning('MyFatoorah Webhook: Failed to decode JSON content.', ['content' => $content]);
            }
        }

        $orderedString = '';

        if ($eventCode == 1) {
            // PAYMENT_STATUS_CHANGED
            // Order: Invoice.Id, Invoice.Status, Transaction.Status, Transaction.PaymentId, Invoice.ExternalIdentifier
            $orderedData = [
                'Invoice.Id' => $data['Invoice']['Id'] ?? '',
                'Invoice.Status' => $data['Invoice']['Status'] ?? '',
                'Transaction.Status' => $data['Transaction']['Status'] ?? '',
                'Transaction.PaymentId' => $data['Transaction']['PaymentId'] ?? '',
                'Invoice.ExternalIdentifier' => $data['Invoice']['ExternalIdentifier'] ?? '',
            ];
            $orderedString = $this->buildSignatureString($orderedData);
        } elseif ($eventCode == 2) {
            // REFUND_STATUS_CHANGED
            // Order: Refund.Id, Refund.Status, Amount.ValueInBaseCurrency, ReferencedInvoice.Id
            $orderedData = [
                'Refund.Id' => $data['Refund']['Id'] ?? '',
                'Refund.Status' => $data['Refund']['Status'] ?? '',
                'Amount.ValueInBaseCurrency' => $data['Amount']['ValueInBaseCurrency'] ?? '',
                'ReferencedInvoice.Id' => $data['ReferencedInvoice']['Id'] ?? '',
            ];
            $orderedString = $this->buildSignatureString($orderedData);
        } else {
            // Unknown event or not documented for signature
            Log::warning("MyFatoorah Webhook: Unknown Event Code $eventCode for signature verification.");
            return false;
        }

        $calculatedSignature = base64_encode(hash_hmac('sha256', $orderedString, $secret, true));

        if (!hash_equals($signature, $calculatedSignature)) {
            Log::warning("MyFatoorah Webhook: Signature verification failed.", [
                'calculated' => $calculatedSignature,
                'received' => $signature,
                'string_used' => $orderedString
            ]);
            return false;
        }

        return true;
    }

    /**
     * Helper to build the ordered signature string.
     */
    private function buildSignatureString(array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            $val = (string) $value;
            $parts[] = "$key=$val";
        }
        return implode(',', $parts);
    }

    /**
     * Handle MyFatoorah Webhook.
     */
    public function handleMyFatoorah(Request $request): JsonResponse
    {
        // 0. Verify Signature (Security)
        if (!$this->verifySignature($request)) {
            Log::warning('MyFatoorah Webhook: Signature verification failed.');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        Log::info('MyFatoorah Webhook Received', $request->all());

        $eventCode = $request->input('Event.Code');
        $data = $request->input('Data', []);

        if ($eventCode == 1) {
            return $this->handlePaymentStatusChanged($data);
        }

        if ($eventCode == 2) {
            return $this->handleRefundStatusChanged($data);
        }

        return response()->json(['success' => true, 'message' => 'Event ignored']);
    }

    private function handlePaymentStatusChanged(array $data): JsonResponse
    {
        // Structure based on provided data model:
        // Data.Invoice.Id (matches local transaction_id)
        // Data.Transaction.PaymentId (Gateway Payment ID)
        // Data.Transaction.Status (SUCCESS, FAILED, etc.)

        $invoiceId = $data['Invoice']['Id'] ?? null;
        $transactionData = $data['Transaction'] ?? [];
        $gatewayPaymentId = $transactionData['PaymentId'] ?? null;
        $status = $transactionData['Status'] ?? null;

        if (!$invoiceId) {
            Log::warning('MyFatoorah Webhook: Invoice ID missing');
            return response()->json(['success' => false, 'message' => 'Invoice ID missing'], 400);
        }

        // Find Payment by Invoice ID (stored as transaction_id)
        $payment = Payment::where('transaction_id', $invoiceId)->first();

        // Fallback: Try ExternalIdentifier if sent as CustomerReference
        if (!$payment && isset($data['Invoice']['ExternalIdentifier'])) {
            $payment = Payment::find($data['Invoice']['ExternalIdentifier']);
        }

        if (!$payment) {
            Log::info("MyFatoorah Webhook: Local payment not found for Invoice ID {$invoiceId}");
            return response()->json(['success' => true, 'message' => 'Payment not found locally, ignored']);
        }

        $existingDetails = $payment->payment_details ?? [];
        $mergedDetails = array_merge($existingDetails, [
            'gateway' => $this->paymentGateway->gatewayKey(),
            'gateway_payment_id' => (string) $gatewayPaymentId,
            'gateway_status' => $status,
            'gateway_data' => $data, // Store full webhook data for audit
            'webhook_received_at' => now()->toIso8601String(),
        ]);

        if ($status === 'SUCCESS') {
            $payment->update([
                'status' => 'completed',
                'payment_details' => $mergedDetails,
            ]);

            // Idempotent: Safe to call even if Callback already ran
            $this->entitlementService->processSuccessfulPayment($payment);

            Log::info("MyFatoorah Webhook: Payment {$payment->id} processed successfully.");
        } else {
            $payment->update([
                'status' => 'failed',
                'payment_details' => $mergedDetails,
            ]);
            Log::info("MyFatoorah Webhook: Payment {$payment->id} marked as failed (Status: $status).");
        }

        return response()->json(['success' => true]);
    }

    private function handleRefundStatusChanged(array $data): JsonResponse
    {
        // Structure:
        // Data.Refund.Id
        // Data.Refund.Status (REFUNDED, CANCELED)
        // Data.ReferencedInvoice.Id (Original Invoice ID)

        $refundData = $data['Refund'] ?? [];
        $referencedInvoice = $data['ReferencedInvoice'] ?? [];

        $invoiceId = $referencedInvoice['Id'] ?? $refundData['InvoiceId'] ?? null;
        $refundStatus = $refundData['Status'] ?? null;

        if (!$invoiceId) {
            return response()->json(['success' => false, 'message' => 'Invoice ID missing for refund'], 400);
        }

        $payment = Payment::where('transaction_id', $invoiceId)->first();

        if (!$payment) {
            Log::info("MyFatoorah Refund Webhook: Local payment not found for Invoice ID {$invoiceId}");
            return response()->json(['success' => true, 'message' => 'Payment not found locally']);
        }

        $existingDetails = $payment->payment_details ?? [];
        $mergedDetails = array_merge($existingDetails, [
            'refund_id' => $refundData['Id'] ?? null,
            'refund_status' => $refundStatus,
            'refund_data' => $data,
            'refunded_at' => now()->toIso8601String(),
        ]);

        if ($refundStatus === 'REFUNDED') {
            $payment->update([
                'status' => 'refunded', // Ensure this status is handled in your system
                'payment_details' => $mergedDetails,
            ]);
            Log::info("MyFatoorah Webhook: Payment {$payment->id} marked as refunded.");

            // Revoke the entitlement
            $this->entitlementService->revokeEntitlement($payment, 'Refunded via MyFatoorah Webhook');
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle Paymob webhook.
     */
    public function handlePaymob(Request $request): JsonResponse
    {
        Log::info('Paymob Webhook Received', [
            'query' => $request->query(),
            'payload' => $request->all()
        ]);

        $hmac = $request->query('hmac');
        $data = $request->input('obj');

        if (!$data) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // Verify HMAC
        $hmacSecret = Setting::get('paymob_hmac_secret', config('services.paymob.hmac_secret'));
        if ($hmacSecret && !$this->verifyPaymobHmac($request->all(), $hmacSecret, $hmac)) {
            Log::error('Paymob Webhook: HMAC verification failed');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $gatewayPaymentId = (string) ($data['id'] ?? '');
        $status = ($data['success'] === true && $data['pending'] === false) ? 'paid' : 'failed';
        $customerReference = $data['order']['extra_data']['customer_reference'] ?? null;

        if (!$customerReference) {
            Log::error('Paymob Webhook: Customer reference (payment ID) missing');

            return response()->json(['message' => 'Missing reference'], 400);
        }

        $payment = Payment::find($customerReference);
        if (!$payment) {
            Log::error('Paymob Webhook: Payment not found for reference: ' . $customerReference);

            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Update payment
        $existingDetails = $payment->payment_details ?? [];
        $payment->update([
            'status' => $status === 'paid' ? 'completed' : 'failed',
            'payment_details' => array_merge($existingDetails, [
                'gateway' => 'paymob',
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_status' => $status,
                'gateway_data' => $data,
                'webhook_received_at' => now()->toIso8601String(),
            ]),
            'transaction_id' => $gatewayPaymentId,
        ]);

        if ($status === 'paid') {
            try {
                $this->entitlementService->processSuccessfulPayment($payment);
            } catch (\Throwable $e) {
                Log::error('Paymob Webhook Error Processing Entitlement: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }

    private function verifyPaymobHmac(array $payload, string $secret, ?string $hmac): bool
    {
        if (!$hmac) {
            return false;
        }

        $obj = $payload['obj'] ?? [];

        // Paymob HMAC concatenation order for transaction webhook
        $keys = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order_id', // This is actually obj.order.id
            'owner',
            'pending',
            'source_data_pan', // This is obj.source_data.pan
            'source_data_sub_type', // This is obj.source_data.sub_type
            'source_data_type', // This is obj.source_data.type
            'success'
        ];

        $concatenatedString = '';
        foreach ($keys as $key) {
            $val = '';
            if ($key === 'order_id') {
                $val = $obj['order']['id'] ?? '';
            } elseif (str_starts_with($key, 'source_data_')) {
                $subKey = str_replace('source_data_', '', $key);
                $val = $obj['source_data'][$subKey] ?? '';
            } else {
                $val = $obj[$key] ?? '';
            }

            if (is_bool($val)) {
                $val = $val ? 'true' : 'false';
            }

            $concatenatedString .= $val;
        }

        $calculatedHmac = hash_hmac('sha512', $concatenatedString, $secret);

        return hash_equals($calculatedHmac, $hmac);
    }
}
