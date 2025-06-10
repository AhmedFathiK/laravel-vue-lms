<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * Display a listing of the user's receipts (billing history).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->receipts()->with('payment');

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

        return response()->json($receipts);
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

        return response()->json([
            'receipt' => $receipt->load('payment'),
        ]);
    }

    /**
     * Download the specified receipt as PDF.
     */
    public function download(Request $request, Receipt $receipt): JsonResponse
    {
        // Ensure the receipt belongs to the authenticated user
        if ($receipt->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to download this receipt',
            ], 403);
        }

        // This would typically generate a PDF and return it
        // For now, we'll just return a success message
        return response()->json([
            'message' => 'Receipt download functionality will be implemented',
            'receipt' => $receipt->load('payment'),
        ]);
    }
}
