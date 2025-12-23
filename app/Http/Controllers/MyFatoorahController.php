<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\MyFatoorahService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MyFatoorahController extends Controller
{
    protected $myFatoorahService;

    public function __construct(MyFatoorahService $myFatoorahService)
    {
        $this->myFatoorahService = $myFatoorahService;
    }

    /**
     * Initiate Payment
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.1',
            'currency' => 'nullable|string|size:3',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $user = $request->user();
        $amount = $request->amount;
        $currency = $request->currency ?? 'KWD';

        // Create local payment record
        $payment = Payment::create([
            'user_id' => $user ? $user->id : null,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'payment_method' => 'myfatoorah',
            'payment_provider' => 'myfatoorah',
            'payment_details' => [
                'plan_id' => $request->plan_id,
                'course_id' => $request->course_id,
            ],
        ]);

        try {
            $data = [
                'PaymentMethodId' => '0', // 0 for MyFatoorah Invoice
                'InvoiceValue' => $amount,
                'DisplayCurrencyIso' => $currency,
                'CustomerName' => $user ? $user->name : 'Guest',
                'CustomerEmail' => $user ? $user->email : 'guest@example.com',
                'CallBackUrl' => route('myfatoorah.callback'),
                'ErrorUrl' => route('myfatoorah.error'),
                'CustomerReference' => $payment->id,
                'Language' => 'en',
            ];

            $response = $this->myFatoorahService->executePayment($data);

            if (isset($response['InvoiceId'])) {
                $payment->update(['transaction_id' => $response['InvoiceId']]);
            }

            return response()->json([
                'success' => true,
                'payment_url' => $response['PaymentURL'],
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment Initiation Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Payment Callback
     */
    public function callback(Request $request)
    {
        $paymentId = $request->query('paymentId');

        if (!$paymentId) {
            return response()->json(['success' => false, 'message' => 'Payment ID missing'], 400);
        }

        try {
            $data = $this->myFatoorahService->getPaymentStatus($paymentId, 'PaymentId');

            $localPaymentId = $data['CustomerReference'];
            $status = $data['InvoiceStatus'];

            $payment = Payment::find($localPaymentId);

            if ($payment) {
                $existingDetails = $payment->payment_details ?? [];
                $mergedDetails = array_merge($existingDetails, $data);

                if ($status === 'Paid') {
                    $payment->update([
                        'status' => 'completed',
                        'payment_details' => $mergedDetails,
                        'transaction_id' => $data['InvoiceId'],
                    ]);

                    // Handle Subscription Creation
                    if (isset($existingDetails['plan_id'])) {
                        $plan = SubscriptionPlan::find($existingDetails['plan_id']);
                        if ($plan && $payment->user_id) {
                            $endsAt = null;
                            if ($plan->plan_type === 'monthly') {
                                $endsAt = Carbon::now()->addMonth();
                            } elseif ($plan->plan_type === 'annual') {
                                $endsAt = Carbon::now()->addYear();
                            } elseif ($plan->duration_days) {
                                $endsAt = Carbon::now()->addDays($plan->duration_days);
                            }

                            UserSubscription::create([
                                'user_id' => $payment->user_id,
                                'subscription_plan_id' => $plan->id,
                                'payment_id' => $payment->id,
                                'starts_at' => Carbon::now(),
                                'ends_at' => $endsAt,
                                'status' => 'active',
                                'auto_renew' => $plan->plan_type !== 'one-time',
                            ]);

                            // Generate Receipt
                            Receipt::create([
                                'user_id' => $payment->user_id,
                                'payment_id' => $payment->id,
                                'receipt_number' => Receipt::generateUniqueReceiptNumber(),
                                'item_type' => 'subscription_plan',
                                'item_id' => $plan->id,
                                'item_name' => $plan->name,
                                'amount' => $payment->amount,
                                'currency' => $payment->currency,
                            ]);
                        }
                    }

                    // Redirect to frontend success page
                    $redirectUrl = '/?payment=success&id=' . $payment->id;
                    if (isset($existingDetails['course_id'])) {
                        $redirectUrl = '/courses/' . $existingDetails['course_id'] . '?payment=success';
                    }

                    return redirect($redirectUrl);
                } else {
                    $payment->update([
                        'status' => 'failed',
                        'payment_details' => $mergedDetails,
                    ]);

                    $redirectUrl = '/?payment=failed&id=' . $payment->id;
                    if (isset($existingDetails['course_id'])) {
                        $redirectUrl = '/courses/' . $existingDetails['course_id'] . '?payment=failed';
                    }

                    return redirect($redirectUrl);
                }
            }

            return response()->json(['success' => false, 'message' => 'Payment record not found']);
        } catch (\Exception $e) {
            Log::error('Payment Callback Failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Payment verification failed'], 500);
        }
    }

    /**
     * Payment Error
     */
    public function error(Request $request)
    {
        $paymentId = $request->query('paymentId');

        Log::error('Payment Failed Callback for PaymentId: ' . $paymentId);

        // You might want to update the local payment status here as well if you can retrieve it

        return redirect('/?payment=error');
    }
}
