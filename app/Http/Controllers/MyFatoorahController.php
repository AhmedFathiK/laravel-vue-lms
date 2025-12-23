<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MyFatoorahService;
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
                if ($status === 'Paid') {
                    $payment->update([
                        'status' => 'completed',
                        'payment_details' => $data,
                        'transaction_id' => $data['InvoiceId'], // Update with actual invoice ID if needed
                    ]);
                    
                    // Redirect to frontend success page
                    // Replace with your actual frontend URL
                    return redirect('/?payment=success&id=' . $payment->id);
                } else {
                    $payment->update([
                        'status' => 'failed',
                        'payment_details' => $data,
                    ]);
                    
                    return redirect('/?payment=failed&id=' . $payment->id);
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
