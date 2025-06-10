<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('permission:view.payment', ['only' => ['index', 'show']]);
        $this->middleware('permission:configure.pricing', ['only' => ['store', 'update', 'destroy']]);
    }

    /**
     * Display a listing of subscription plans.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SubscriptionPlan::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by billing cycle
        if ($request->has('billing_cycle')) {
            $query->where('billing_cycle', $request->billing_cycle);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $plans = $query->orderBy('price', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json($plans);
    }

    /**
     * Store a newly created subscription plan.
     */
    public function store(SubscriptionPlanRequest $request): JsonResponse
    {
        $plan = SubscriptionPlan::create($request->validated());

        return response()->json([
            'message' => 'Subscription plan created successfully',
            'plan' => $plan,
        ], 201);
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        return response()->json([
            'plan' => $subscriptionPlan,
        ]);
    }

    /**
     * Update the specified subscription plan.
     */
    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $subscriptionPlan->update($request->validated());

        return response()->json([
            'message' => 'Subscription plan updated successfully',
            'plan' => $subscriptionPlan,
        ]);
    }

    /**
     * Remove the specified subscription plan.
     */
    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        // Check if the plan has active subscriptions
        if ($subscriptionPlan->subscriptions()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Cannot delete a subscription plan with active subscriptions',
            ], 422);
        }

        $subscriptionPlan->delete();

        return response()->json([
            'message' => 'Subscription plan deleted successfully',
        ]);
    }
}
