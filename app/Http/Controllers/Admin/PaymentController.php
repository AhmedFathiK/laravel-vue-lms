<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentRequest;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('permission:view.payments', ['only' => ['index', 'show']]);
        $this->middleware('permission:manage.subscriptions', ['only' => ['store', 'update', 'destroy']]);
    }

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['user', 'receipt']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by transaction ID
        if ($request->has('search')) {
            $query->where('transaction_id', 'like', '%' . $request->search . '%');
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(PaymentRequest $request): JsonResponse
    {
        $payment = Payment::create($request->validated());

        // Generate receipt automatically
        if ($payment->status === 'completed') {
            $this->generateReceipt($payment, $request->item_type, $request->item_id, $request->item_name);
        }

        return response()->json([
            'message' => 'Payment created successfully',
            'payment' => $payment->load('receipt'),
        ], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment): JsonResponse
    {
        return response()->json([
            'payment' => $payment->load(['user', 'receipt']),
        ]);
    }

    /**
     * Update the specified payment.
     */
    public function update(PaymentRequest $request, Payment $payment): JsonResponse
    {
        $oldStatus = $payment->status;
        $payment->update($request->validated());

        // Generate receipt if payment status changed to completed
        if ($oldStatus !== 'completed' && $payment->status === 'completed' && !$payment->receipt) {
            $this->generateReceipt($payment, $request->item_type, $request->item_id, $request->item_name);
        }

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => $payment->load('receipt'),
        ]);
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Payment $payment): JsonResponse
    {
        // Check if payment can be deleted
        if ($payment->status === 'completed') {
            return response()->json([
                'message' => 'Completed payments cannot be deleted',
            ], 422);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }

    /**
     * Generate a receipt for a payment.
     */
    private function generateReceipt(Payment $payment, ?string $itemType, ?int $itemId, ?string $itemName): Receipt
    {
        return Receipt::create([
            'user_id' => $payment->user_id,
            'payment_id' => $payment->id,
            'receipt_number' => Receipt::generateUniqueReceiptNumber(),
            'item_type' => $itemType,
            'item_id' => $itemId,
            'item_name' => $itemName ?? 'Payment',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ]);
    }
}
