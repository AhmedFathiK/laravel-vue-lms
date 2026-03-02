<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateEntitlementException;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\BillingPlan;
use App\Models\UserEntitlement;
use App\Services\Payment\Currency;
use App\Services\Payments\PaymentServiceInterface;
use App\Services\EntitlementService;
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
        private readonly EntitlementService $entitlementService
    ) {}

    /**
     * Initiate a payment checkout session.
     */
    public function checkout(Request $request): JsonResponse
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:0.1'],
            'currency' => Currency::validationRules(required: false),
            'plan_id' => ['nullable', 'exists:billing_plans,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'payment_method_id' => ['nullable', 'string'],
            'renew_entitlement_id' => ['nullable', 'exists:user_entitlements,id'],
            'upgrade_from_entitlement_id' => ['nullable', 'exists:user_entitlements,id'],
            'phone' => ['nullable', 'string', 'max:20'],
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
                'billing_plan_id' => $validated['plan_id'] ?? null,
                'course_id' => $validated['course_id'] ?? null,
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'renew_entitlement_id' => $validated['renew_entitlement_id'] ?? null,
                'upgrade_from_entitlement_id' => $validated['upgrade_from_entitlement_id'] ?? null,
            ],
        ]);

        try {
            $items = null;
            if (!empty($validated['plan_id'])) {
                $plan = BillingPlan::find($validated['plan_id']);
                if ($plan) {
                    $courseName = null;
                    if (!empty($validated['course_id'])) {
                        $course = \App\Models\Course::find($validated['course_id']);
                        $courseName = $course?->title;
                    } elseif ($plan->courses()->exists()) {
                        $courseName = $plan->courses()->first()?->title;
                    }

                    $period = match ($plan->access_type) {
                        'lifetime' => 'Lifetime',
                        'fixed' => $plan->access_duration_days . ' days',
                        'while_active' => match ($plan->billing_interval) {
                            'month' => '30 days',
                            'year' => '365 days',
                            default => $plan->access_duration_days . ' days',
                        },
                        default => '',
                    };

                    $itemName = $courseName
                        ? "{$courseName} - {$plan->name}({$period})"
                        : "{$plan->name}({$period})";

                    $items = [
                        [
                            'name' => $itemName,
                            'quantity' => 1,
                            'price' => $amount,
                        ]
                    ];
                }
            }

            $checkout = $this->paymentGateway->createCheckout(
                amount: $amount,
                currency: $currency,
                customer: [
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'phone' => $validated['phone'] ?? $user?->phone,
                ],
                metadata: [
                    'customer_reference' => (string) $payment->id,
                ],
                callbackUrl: route('payments.callback'),
                errorUrl: route('payments.error'),
                paymentMethodId: $validated['payment_method_id'] ?? null,
                items: $items
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
     * Handle successful payment callback from gateway.
     */
    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        Log::info('Payment Gateway Success Callback Received', [
            'query' => $request->query(),
            'all' => $request->all()
        ]);

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

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle gateway error callback.
     */
    public function error(Request $request): RedirectResponse|JsonResponse
    {
        Log::info('Payment Gateway Error Callback Received', [
            'query' => $request->query(),
            'all' => $request->all()
        ]);

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
            $this->entitlementService->processSuccessfulPayment($payment);
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
