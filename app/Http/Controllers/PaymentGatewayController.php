<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateSubscriptionException;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Payment\Currency;
use App\Services\Payments\PaymentServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gateway-agnostic payment controller using a configurable gateway implementation.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentGateway,
        private readonly \App\Services\SubscriptionService $subscriptionService
    ) {}

    /**
     * Initiate a payment checkout session.
     */
    public function checkout(Request $request): JsonResponse
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:0.1'],
            'currency' => Currency::validationRules(required: false),
            'plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'payment_method_id' => ['nullable', 'string'],
        ];

        $validated = $request->validate($rules);

        $user = $request->user();
        $amount = (float) $validated['amount'];
        $currency = Currency::normalize((string) ($validated['currency'] ?? Currency::default()));

        $payment = Payment::create([
            'user_id' => $user?->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'payment_method' => $this->paymentGateway->gatewayKey(),
            'payment_provider' => $this->paymentGateway->gatewayKey(),
            'payment_details' => [
                'plan_id' => $validated['plan_id'] ?? null,
                'course_id' => $validated['course_id'] ?? null,
                'payment_method_id' => $validated['payment_method_id'] ?? null,
            ],
        ]);

        try {
            $checkout = $this->paymentGateway->createCheckout(
                amount: $amount,
                currency: $currency,
                customer: [
                    'name' => $user?->name,
                    'email' => $user?->email,
                ],
                metadata: [
                    'customer_reference' => (string) $payment->id,
                ],
                callbackUrl: 'http://127.0.0.1:8000/api/payments/callback1',
                errorUrl: route('payments.error'),
                paymentMethodId: $validated['payment_method_id'] ?? null
            );

            if (!empty($checkout['transaction_id'])) {
                $payment->update(['transaction_id' => (string) $checkout['transaction_id']]);
            }

            return response()->json([
                'success' => true,
                'payment_url' => $checkout['payment_url'] ?? null,
                'payment_id' => $payment->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment Initiation Failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available payment methods.
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.1'],
            'currency' => Currency::validationRules(required: false),
        ]);

        $amount = (float) $validated['amount'];
        $currency = Currency::normalize((string) ($validated['currency'] ?? Currency::default()));

        try {
            $methods = $this->paymentGateway->getPaymentMethods($amount, $currency);

            return response()->json([
                'success' => true,
                'data' => $methods,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch payment methods: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods',
            ], 500);
        }
    }

    /**
     * Handle gateway callback.
     */
    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        Log::info('Payment Callback Hit', $request->all());

        $gatewayPaymentId = $request->query('payment_id') ?? $request->query('id') ?? $request->query('paymentId');
        if (!$gatewayPaymentId) {
            Log::error('Payment Callback: Payment ID missing in request', $request->all());

            return response()->json(['success' => false, 'message' => 'Payment ID missing'], 400);
        }

        try {
            $statusData = $this->paymentGateway->getPaymentStatus((string) $gatewayPaymentId);

            $localPaymentId = $statusData['local_payment_id'] ?? null;
            $normalizedStatus = (string) ($statusData['status'] ?? 'failed');
            $transactionId = $statusData['transaction_id'] ?? null;

            $payment = $localPaymentId ? Payment::find($localPaymentId) : null;
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment record not found'], 404);
            }

            $existingDetails = $payment->payment_details ?? [];
            $mergedDetails = array_merge($existingDetails, [
                'gateway' => $this->paymentGateway->gatewayKey(),
                'gateway_payment_id' => (string) $gatewayPaymentId,
                'gateway_status' => $normalizedStatus,
                'gateway_data' => $statusData['gateway_data'] ?? null,
            ]);

            if ($normalizedStatus === 'paid') {
                $payment->update([
                    'status' => 'completed',
                    'payment_details' => $mergedDetails,
                    'transaction_id' => $transactionId ?? $payment->transaction_id,
                ]);

                $this->handlePostPayment($payment);

                return redirect($this->successRedirectUrl($existingDetails, $payment->id));
            }

            $payment->update([
                'status' => 'failed',
                'payment_details' => $mergedDetails,
            ]);

            return redirect($this->failedRedirectUrl($existingDetails, $payment->id));
        } catch (\Throwable $e) {
            Log::error('Payment Callback Failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Payment verification failed'], 500);
        }
    }

    /**
     * Handle gateway error callback.
     */
    public function error(Request $request): RedirectResponse|JsonResponse
    {
        $gatewayPaymentId = $request->query('payment_id') ?? $request->query('id') ?? $request->query('paymentId');

        Log::error('Payment Failed Callback for gateway payment id: ' . (string) $gatewayPaymentId);

        if (!$gatewayPaymentId) {
            return redirect('/?payment=error&reason=missing_id');
        }

        try {
            $statusData = $this->paymentGateway->getPaymentStatus((string) $gatewayPaymentId);

            $localPaymentId = $statusData['local_payment_id'] ?? null;
            $normalizedStatus = (string) ($statusData['status'] ?? 'failed');

            $gatewayError = 'Unknown gateway error';
            if (isset($statusData['gateway_data']['InvoiceTransactions'][0]['Error'])) {
                $gatewayError = $statusData['gateway_data']['InvoiceTransactions'][0]['Error'];
            } elseif (isset($statusData['gateway_data']['Data']['Error'])) {
                $gatewayError = $statusData['gateway_data']['Data']['Error'];
            }

            $payment = $localPaymentId ? Payment::find($localPaymentId) : null;

            if ($payment) {
                $existingDetails = $payment->payment_details ?? [];
                $mergedDetails = array_merge($existingDetails, [
                    'gateway' => $this->paymentGateway->gatewayKey(),
                    'gateway_payment_id' => (string) $gatewayPaymentId,
                    'gateway_status' => $normalizedStatus,
                    'gateway_data' => $statusData['gateway_data'] ?? null,
                    'failure_reason' => $gatewayError,
                ]);

                $payment->update([
                    'status' => 'failed',
                    'payment_details' => $mergedDetails,
                ]);

                return redirect($this->failedRedirectUrl($existingDetails, $payment->id));
            }
        } catch (\Throwable $e) {
            Log::error('Payment Error Callback Processing Failed: ' . $e->getMessage());
        }

        return redirect('/?payment=error');
    }

    private function handlePostPayment(Payment $payment): void
    {
        try {
            $this->subscriptionService->processSuccessfulPayment($payment);
        } catch (\Throwable $e) {
            Log::error("Failed to process post-payment actions for Payment {$payment->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function successRedirectUrl(array $existingDetails, int $paymentId): string
    {
        if (isset($existingDetails['course_id'])) {
            return '/courses/' . $existingDetails['course_id'] . '?payment=success&payment_id=' . $paymentId;
        }

        return '/?payment=success&id=' . $paymentId;
    }

    private function failedRedirectUrl(array $existingDetails, int $paymentId): string
    {
        if (isset($existingDetails['course_id'])) {
            return '/courses/' . $existingDetails['course_id'] . '?payment=failed&payment_id=' . $paymentId;
        }

        return '/?payment=failed&id=' . $paymentId;
    }
}
