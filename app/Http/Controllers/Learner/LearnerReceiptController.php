<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Http\Resources\ReceiptResource;
use App\Services\ReceiptPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearnerReceiptController extends Controller
{
    protected $receiptPdfService;

    public function __construct(ReceiptPdfService $receiptPdfService)
    {
        $this->receiptPdfService = $receiptPdfService;
    }

    /**
     * Display a listing of the user's receipts (billing history).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->receipts()->with(['payment', 'course', 'billingPlan', 'voidedBy', 'entitlement.billingPlan']);

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Filter by item type
        if ($request->has('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        $receipts = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json(ReceiptResource::collection($receipts)->response()->getData(true));
    }

    /**
     * Display the specified receipt.
     */
    public function show(Request $request, Receipt $receipt): JsonResponse
    {
        // Ensure the receipt belongs to the authenticated user
        if ($receipt->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to view this receipt',
            ], 403);
        }

        $receipt->load(['payment.entitlement.billingPlan', 'course', 'billingPlan', 'voidedBy', 'entitlement.billingPlan']);

        return response()->json(new ReceiptResource($receipt));
    }

    /**
     * Download the specified receipt as PDF.
     */
    public function download(Request $request, Receipt $receipt)
    {
        // Ensure the receipt belongs to the authenticated user
        if ($receipt->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to download this receipt',
            ], 403);
        }

        $pdf = $this->receiptPdfService->generate($receipt);
        return $pdf->download("receipt-{$receipt->receipt_number}.pdf");
    }
}
