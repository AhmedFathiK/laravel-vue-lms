<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DuplicateEntitlementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelUserEntitlementRequest;
use App\Http\Requests\Admin\DestroyUserEntitlementRequest;
use App\Http\Requests\Admin\StoreUserEntitlementRequest;
use App\Http\Requests\Admin\UpdateUserEntitlementRequest;
use App\Http\Requests\Admin\ViewUserEntitlementRequest;
use App\Models\Payment;
use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;

use App\Http\Resources\UserEntitlementResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserEntitlementController extends Controller
{
    protected $entitlementService;

    public function __construct(EntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Display a listing of user entitlements.
     */
    public function index(ViewUserEntitlementRequest $request): AnonymousResourceCollection
    {
        $query = UserEntitlement::with(['user', 'billingPlan', 'payment']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by payment ID
        if ($request->has('payment_id')) {
            $query->where('payment_id', $request->payment_id);
        }

        // Filter by billing plan
        if ($request->has('billing_plan_id')) {
            $query->where('billing_plan_id', $request->billing_plan_id);
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

        $perPage = $request->per_page ?? 15;
        
        return UserEntitlementResource::collection(
            $query->orderBy('created_at', 'desc')->paginate($perPage)
        );
    }

    /**
     * Store a newly created user entitlement.
     */
    public function store(StoreUserEntitlementRequest $request): UserEntitlementResource|JsonResponse
    {
        $user = User::findOrFail($request->user_id);
        $plan = BillingPlan::findOrFail($request->billing_plan_id ?? $request->billing_plan_id);
        $payment = $request->payment_id ? Payment::findOrFail($request->payment_id) : null;

        try {
            $entitlement = $this->entitlementService->grantEntitlement($user, $plan, $payment);

            return new UserEntitlementResource($entitlement->load(['user', 'billingPlan', 'payment']));

        } catch (\Exception $e) {
             return response()->json(['message' => 'Failed to grant entitlement: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified user entitlement.
     */
    public function show(ViewUserEntitlementRequest $request, UserEntitlement $userEntitlement): UserEntitlementResource
    {
        return new UserEntitlementResource($userEntitlement->load(['user', 'billingPlan', 'payment']));
    }

    /**
     * Update the specified user entitlement.
     */
    public function update(UpdateUserEntitlementRequest $request, UserEntitlement $userEntitlement): UserEntitlementResource
    {
        $userEntitlement->update($request->validated());

        return new UserEntitlementResource($userEntitlement->load(['user', 'billingPlan', 'payment']));
    }

    /**
     * Cancel the specified user entitlement.
     */
    public function cancel(CancelUserEntitlementRequest $request, UserEntitlement $userEntitlement): UserEntitlementResource|JsonResponse
    {
        if ($userEntitlement->status !== 'active') {
            return response()->json([
                'message' => 'Only active entitlements can be revoked',
            ], 422);
        }

        $userEntitlement->update([
            'status' => 'revoked',
            'ends_at' => now(), // Immediate revocation
        ]);

        return new UserEntitlementResource($userEntitlement->load(['user', 'billingPlan', 'payment']));
    }

    /**
     * Remove the specified user entitlement.
     */
    public function destroy(DestroyUserEntitlementRequest $request, UserEntitlement $userEntitlement): JsonResponse
    {
        $userEntitlement->delete();

        return response()->json([
            'message' => 'User entitlement deleted successfully',
        ]);
    }
}
