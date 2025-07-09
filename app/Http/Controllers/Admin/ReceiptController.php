<?php

namespace App\Http\Controllers\Admin;

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
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
        $query = Receipt::with(['user', 'payment', 'course', 'subscriptionPlan.course']);

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
            'totalItems' => $receipts->total(),
            'currentPage' => $receipts->currentPage(),
            'perPage' => $receipts->perPage(),
            'lastPage' => $receipts->lastPage(),
        ]);
        return response()->json();
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
            $payment = \App\Models\Payment::create([
                'user_id' => $validated['user_id'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'currency' => $request->currency ?? 'USD',
                'status' => 'completed',
                'transaction_id' => 'MANUAL-' . time(),
                'payment_provider' => 'Manual',
                'payment_details' => [
                    'notes' => $validated['notes'],
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
                'currency' => $request->currency ?? 'USD',
            ]);

            // Create subscription if requested
            if ($request->link_subscription) {
                $this->subscriptionService->create($user, $plan, $payment->id);
            }

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
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create receipt',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified receipt.
     */
    public function show(ViewReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        $receipt->load(['user', 'payment']);

        // Load subscription if exists
        $subscription = null;
        if ($receipt->payment) {
            $subscription = $receipt->payment->subscription;
            if ($subscription) {
                $subscription->load('plan');
            }
        }

        return response()->json([
            'receipt' => $receipt,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Update the specified receipt in storage.
     */
    public function update(UpdateReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        // Block editing of system-generated receipts
        if ($receipt->source_type !== 'manual') {
            return response()->json(['message' => 'System-generated receipts cannot be edited.'], 403);
        }

        $validated = $request->validated();

        // Check if the receipt is linked to a subscription
        $isLinkedToSubscription = DB::table('user_subscriptions')->where('payment_id', $receipt->payment_id)->exists();

        if ($isLinkedToSubscription) {
            // Validate restricted fields
            $restrictedFields = ['user_id', 'course_id', 'plan_id', 'amount'];
            foreach ($restrictedFields as $field) {
                if ($request->has($field) && $request->input($field) !== $receipt->{$field}) {
                    return response()->json(['message' => "Cannot update {$field} because the receipt is linked to a subscription."], 422);
                }
            }
        }

        DB::beginTransaction();

        try {
            // Update payment details
            if ($request->has('payment_method') || $request->has('payment_date') || $request->has('notes')) {
                $paymentDetails = $receipt->payment->payment_details ?? [];
                if ($request->has('notes')) {
                    $paymentDetails['notes'] = $validated['notes'];
                }
                if ($request->has('payment_date')) {
                    $paymentDetails['payment_date'] = $validated['payment_date'];
                }
                $receipt->payment->update([
                    'payment_method' => $validated['payment_method'] ?? $receipt->payment->payment_method,
                    'payment_details' => $paymentDetails,
                ]);
            }

            // Update receipt details
            $updateData = $request->only(array_keys($validated));
            if (!$isLinkedToSubscription) {
                // Allow all fields to be updated if not linked
                $receipt->update($updateData);
                if (isset($updateData['amount'])) {
                    $receipt->payment->update(['amount' => $updateData['amount']]);
                }
            } else {
                // Only allow non-restricted fields to be updated
                $allowedUpdates = collect($updateData)->except(['user_id', 'course_id', 'plan_id', 'amount'])->all();
                if (!empty($allowedUpdates)) {
                    $receipt->update($allowedUpdates);
                }
            }

            // Handle 'create-subscription' logic if it's not linked
            if (!$isLinkedToSubscription && $request->boolean('create_subscription') && isset($validated['plan_id'])) {
                $user = User::findOrFail($validated['user_id']);
                $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
                $this->subscriptionService->create($user, $plan, $receipt->payment_id);
            }

            DB::commit();

            return response()->json([
                'message' => 'Receipt updated successfully.',
                'receipt' => $receipt->fresh(['user', 'payment']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update receipt.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified receipt from storage.
     */
    public function destroy(DeleteReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        // Only allow deleting manual receipts
        if ($receipt->payment->payment_provider !== 'Manual') {
            return response()->json([
                'message' => 'Only manual receipts can be deleted',
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Delete associated subscription if exists and is the only one linked to this payment
            if ($receipt->payment->subscription) {
                $receipt->payment->subscription->delete();
            }

            // Delete payment
            $receipt->payment->delete();

            // Delete receipt
            $receipt->delete();

            DB::commit();

            return response()->json([
                'message' => 'Receipt deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete receipt',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the specified receipt as PDF.
     */
    public function download(ViewReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        // This would typically generate a PDF and return it
        // For now, we'll just return a success message
        return response()->json([
            'message' => 'Receipt download functionality will be implemented',
            'receipt' => $receipt->load(['user', 'payment']),
        ]);
    }

    /**
     * Resend the receipt to the user's email.
     */
    public function resend(ResendReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        try {
            $user = $receipt->user;
            // Email sending logic would go here
            // Mail::to($user->email)->send(new ReceiptCreated($receipt));

            return response()->json([
                'message' => 'Receipt sent successfully to ' . $user->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send receipt',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate the PDF for a receipt.
     */
    public function regeneratePdf(Receipt $receipt): JsonResponse
    {
        try {
            // PDF regeneration logic would go here
            // For now, we'll just return a success message

            return response()->json([
                'message' => 'Receipt PDF regenerated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to regenerate receipt PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get receipt statistics for the admin dashboard.
     */
    public function statistics(): JsonResponse
    {
        try {
            // Get total number of receipts
            $totalReceipts = Receipt::count();

            // Get total amount of all receipts
            $totalAmount = Receipt::sum('amount');

            // Get top payment method
            $topPaymentMethod = DB::table('payments')
                ->join('receipts', 'payments.id', '=', 'receipts.payment_id')
                ->select('payment_method', DB::raw('count(*) as count'))
                ->groupBy('payment_method')
                ->orderBy('count', 'desc')
                ->first();

            // Get count of unique item types
            $itemTypes = Receipt::select('item_type')->distinct()->count();

            return response()->json([
                'totalReceipts' => $totalReceipts,
                'totalAmount' => $totalAmount,
                'topPaymentMethod' => $topPaymentMethod ? $topPaymentMethod->payment_method : null,
                'itemTypes' => $itemTypes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch receipt statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
