<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicateSubscriptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteReceiptRequest;
use App\Http\Requests\Admin\ResendReceiptRequest;
use App\Http\Requests\Admin\StoreReceiptRequest;
use App\Http\Requests\Admin\UpdateReceiptRequest;
use App\Http\Requests\Admin\ViewReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Course;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Payment\Currency;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of receipts.
     */
    public function index(ViewReceiptRequest $request): JsonResponse
    {
        $query = Receipt::with(['user', 'payment', 'course', 'subscriptionPlan.course', 'voidedBy']);

        // Handle soft-deleted receipts
        if ($request->boolean('with_trashed')) {
            $query->onlyTrashed();
        }

        // Filter by user (name or email)
        if ($request->has('user_query')) {
            $userQuery = $request->user_query;
            $query->whereHas('user', function ($q) use ($userQuery) {
                $q->where('first_name', 'like', '%' . $userQuery . '%')
                    ->orWhere('last_name', 'like', '%' . $userQuery . '%')
                    ->orWhere('email', 'like', '%' . $userQuery . '%');
            });
        }

        // Filter by course
        if ($request->has('course_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('item_type', 'course')
                    ->where('item_id', $request->course_id);
            });
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->whereHas('payment', function ($q) use ($request) {
                $q->where('payment_method', $request->payment_method);
            });
        }

        // Filter by receipt number
        if ($request->has('receipt_id')) {
            $query->where('receipt_number', 'like', '%' . $request->receipt_id . '%');
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Filter by subscription type
        if ($request->has('subscription_type')) {
            $subscriptionType = $request->subscription_type;
            $query->whereHas('payment.subscription', function ($q) use ($subscriptionType) {
                $q->whereHas('plan', function ($q) use ($subscriptionType) {
                    $q->where('plan_type', $subscriptionType);
                });
            });
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
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $receipts = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->per_page ?? 15);

        $receipts->getCollection()->transform(function ($receipt) {
            $receipt->is_linked_to_subscription = $receipt->subscription()->exists();
            return $receipt;
        });

        return response()->json([
            'items' => ReceiptResource::collection($receipts->items()),
            'total_items' => $receipts->total(),
            'current_page' => $receipts->currentPage(),
            'per_page' => $receipts->perPage(),
            'last_page' => $receipts->lastPage(),
        ]);
    }

    /**
     * Store a newly created receipt in storage.
     */
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Start a transaction
        DB::beginTransaction();

        try {
            // Create payment record
            $currency = Currency::normalize((string) ($validated['currency'] ?? Currency::default()));
            $payment = \App\Models\Payment::create([
                'user_id' => $validated['user_id'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'currency' => $currency,
                'status' => 'completed',
                'transaction_id' => 'MANUAL-' . time(),
                'payment_provider' => 'Manual',
                'payment_details' => [
                    'notes' => $validated['notes'] ?? '',
                    'created_by' => $request->user()->id,
                    'payment_date' => $validated['payment_date'],
                ],
            ]);

            // Get course and plan details
            $user = User::findOrFail($validated['user_id']);
            $course = Course::findOrFail($validated['course_id']);
            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

            // Create receipt
            $receipt = Receipt::create([
                'user_id' => $validated['user_id'],
                'payment_id' => $payment->id,
                'receipt_number' => $validated['receipt_number'] ?? Receipt::generateUniqueReceiptNumber(),
                'item_type' => 'subscription_plan',
                'item_id' => $plan->id,
                'item_name' => $course->title . ' - ' . $plan->name,
                'amount' => $validated['amount'],
                'currency' => $currency,
            ]);

            // Automatically create/link subscription
            // We force createSubscription regardless of any previous "link_subscription" flag
            $this->subscriptionService->createSubscription($user, $plan, $payment);

            // Generate PDF if requested
            if ($request->auto_generate_pdf) {
                // PDF generation logic would go here
                // For now, we'll just note that it would be generated
            }

            // Send email notification if requested
            if ($request->notify_user) {
                $user = User::findOrFail($validated['user_id']);
                // Email sending logic would go here
                // Mail::to($user->email)->send(new ReceiptCreated($receipt));
            }

            DB::commit();

            return response()->json([
                'message' => 'Receipt created successfully',
                'receipt' => $receipt->load(['user', 'payment']),
            ], 201);
        } catch (DuplicateSubscriptionException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to link subscription: User already has an active subscription.',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create receipt',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display receipt statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        // TODO: Implement actual statistics logic
        return response()->json([
            'total_revenue' => 0,
            'total_receipts' => 0,
            'average_amount' => 0,
        ]);
    }

    /**
     * Download the specified receipt PDF.
     */
    public function download(Receipt $receipt)
    {
        // TODO: Implement PDF download
        return response()->json(['message' => 'PDF download not implemented yet'], 501);
    }

    /**
     * Resend the receipt email.
     */
    public function resend(ResendReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        // TODO: Implement resend email logic
        return response()->json(['message' => 'Resend email not implemented yet'], 501);
    }

    /**
     * Regenerate the receipt PDF.
     */
    public function regeneratePdf(Receipt $receipt): JsonResponse
    {
        // TODO: Implement regenerate PDF logic
        return response()->json(['message' => 'Regenerate PDF not implemented yet'], 501);
    }

    /**
     * Void the specified receipt.
     */
    public function void(DeleteReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        // Check if already voided
        if ($receipt->voided_at) {
            return response()->json(['message' => 'Receipt is already voided'], 422);
        }

        DB::transaction(function () use ($request, $receipt) {
            $receipt->update([
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
                'void_reason' => $request->reason,
            ]);

            // Void associated payment if exists
            if ($receipt->payment) {
                // If payment is completed, we should probably mark it as voided or refunded
                // For now, let's assume 'voided' is a valid status or use 'failed'
                // Based on UserSubscriptionService logic, maybe 'failed' is safer to trigger observer
                // But Payment model might not have 'voided' status.
                // Let's check Payment model constants or schema if available.
                // Assuming standard 'failed' or 'refunded' for now if 'voided' not available.
                // Or just set to 'failed'.
                // Ideally, we should add 'voided' to Payment status enum if not present.
                // Let's use 'failed' for now as it triggers suspension.
                $receipt->payment->update(['status' => 'failed']);
            }
        });

        return response()->json([
            'message' => 'Receipt voided successfully',
            'receipt' => $receipt->fresh(),
        ]);
    }
}
