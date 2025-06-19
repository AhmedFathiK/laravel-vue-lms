<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('permission:view.payments', ['only' => ['index', 'show']]);
    }

    /**
     * Display a listing of receipts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Receipt::with(['user', 'payment']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by receipt number or item name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', '%' . $search . '%')
                    ->orWhere('item_name', 'like', '%' . $search . '%');
            });
        }

        // Filter by item type
        if ($request->has('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        $receipts = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($receipts);
    }

    /**
     * Display the specified receipt.
     */
    public function show(Receipt $receipt): JsonResponse
    {
        return response()->json([
            'receipt' => $receipt->load(['user', 'payment']),
        ]);
    }

    /**
     * Download the specified receipt as PDF.
     */
    public function download(Receipt $receipt): JsonResponse
    {
        // This would typically generate a PDF and return it
        // For now, we'll just return a success message
        return response()->json([
            'message' => 'Receipt download functionality will be implemented',
            'receipt' => $receipt->load(['user', 'payment']),
        ]);
    }
}
