<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelUserSubscriptionRequest;
use App\Http\Requests\Admin\DestroyUserSubscriptionRequest;
use App\Http\Requests\Admin\StoreUserSubscriptionRequest;
use App\Http\Requests\Admin\UpdateUserSubscriptionRequest;
use App\Http\Requests\Admin\ViewUserSubscriptionRequest;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;

class UserSubscriptionController extends Controller
{
    /**
     * Display a listing of user subscriptions.
     */
    public function index(ViewUserSubscriptionRequest $request): JsonResponse
    {
        $query = UserSubscription::with(['user', 'plan.course', 'payment']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by payment ID
        if ($request->has('payment_id')) {
            $query->where('payment_id', $request->payment_id);
        }

        // Filter by subscription plan
        if ($request->has('subscription_plan_id')) {
            $query->where('subscription_plan_id', $request->subscription_plan_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range for start date
        if ($request->has('from_start_date')) {
            $query->whereDate('starts_at', '>=', $request->from_start_date);
        }

        if ($request->has('to_start_date')) {
            $query->whereDate('starts_at', '<=', $request->to_start_date);
        }

        // Filter by auto-renew
        if ($request->has('auto_renew')) {
            $query->where('auto_renew', $request->boolean('auto_renew'));
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($subscriptions);
    }

    /**
     * Store a newly created user subscription.
     */
    public function store(StoreUserSubscriptionRequest $request): JsonResponse
    {
        $subscription = UserSubscription::create($request->validated());

        return response()->json([
            'message' => 'User subscription created successfully',
            'subscription' => $subscription->load(['user', 'plan.course', 'payment']),
        ], 201);
    }

    /**
     * Display the specified user subscription.
     */
    public function show(ViewUserSubscriptionRequest $request, UserSubscription $userSubscription): JsonResponse
    {
        return response()->json([
            'subscription' => $userSubscription->load(['user', 'plan.course', 'payment']),
        ]);
    }

    /**
     * Update the specified user subscription.
     */
    public function update(UpdateUserSubscriptionRequest $request, UserSubscription $userSubscription): JsonResponse
    {
        $userSubscription->update($request->validated());

        return response()->json([
            'message' => 'User subscription updated successfully',
            'subscription' => $userSubscription->load(['user', 'plan.course', 'payment']),
        ]);
    }

    /**
     * Cancel the specified user subscription.
     */
    public function cancel(CancelUserSubscriptionRequest $request, UserSubscription $userSubscription): JsonResponse
    {
        if ($userSubscription->status !== 'active') {
            return response()->json([
                'message' => 'Only active subscriptions can be canceled',
            ], 422);
        }

        $userSubscription->update([
            'status' => 'canceled',
            'auto_renew' => false,
            'cancellation_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'User subscription canceled successfully',
            'subscription' => $userSubscription->load(['user', 'plan.course', 'payment']),
        ]);
    }

    /**
     * Remove the specified user subscription.
     */
    public function destroy(DestroyUserSubscriptionRequest $request, UserSubscription $userSubscription): JsonResponse
    {
        // Check if subscription can be deleted
        if ($userSubscription->status === 'active') {
            return response()->json([
                'message' => 'Active subscriptions cannot be deleted. Cancel the subscription first.',
            ], 422);
        }

        $userSubscription->delete();

        return response()->json([
            'message' => 'User subscription deleted successfully',
        ]);
    }
}
