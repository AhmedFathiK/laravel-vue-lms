<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Exceptions\DuplicateEntitlementException;
use App\Http\Resources\EntitlementPlanResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\BillingPlan;
use App\Models\UserEntitlement;
use App\Services\Payment\Currency;
use App\Services\Payments\PaymentServiceInterface;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LearnerEntitlementController extends Controller
{
    /**
     * Display a listing of the user's entitlements.
     */
    public function index(): JsonResponse
    {
        $entitlements = UserEntitlement::where('user_id', Auth::id())
            ->with(['billingPlan.courses'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(\App\Http\Resources\UserEntitlementResource::collection($entitlements));
    }

    /**
     * Get all enrollments for the current user.
     */
    public function getEnrolledCourses(Request $request): JsonResponse
    {
        $query = CourseEnrollment::where('user_id', Auth::id())
            ->with([
                'course' => function ($query) {
                    $query->where('status', 'published')
                        ->select('id', 'title', 'description', 'thumbnail');
                },
                'userEntitlement.billingPlan'
            ]);

        // Apply filters
        if ($request->has('completed')) {
            $query->where('completed_at', "!=", null);
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'last_accessed_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        try {
            $enrollments = $query->get();
        } catch (\Throwable $th) {
            throw $th;
        }


        return response()->json($enrollments);
    }

    /**
     * Display the specified entitlement.
     */
    public function show(UserEntitlement $entitlement): JsonResponse
    {
        // Ensure the entitlement belongs to the authenticated user
        if ($entitlement->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to entitlement');
        }

        $entitlement->load(['billingPlan', 'payment']);

        return response()->json($entitlement);
    }

    /**
     * Acquire a plan (entitlement).
     */
    public function acquire(
        Request $request,
        EntitlementService $entitlementService,
        PaymentServiceInterface $paymentService
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:billing_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $plan = BillingPlan::findOrFail($request->plan_id);

        // Check if the plan is active
        if (!$plan->is_active) {
            return response()->json([
                'message' => 'This billing plan is not available',
            ], 422);
        }

        // 1. Free Plan Logic
        if ($plan->billing_type === 'free' || $plan->price <= 0) {
            try {
                $entitlement = $entitlementService->grantEntitlement($user, $plan);

                return response()->json([
                    'message' => 'Successfully acquired the entitlement',
                    'entitlement' => $entitlement->load(['billingPlan']),
                    'status' => 'enrolled',
                ], 201);
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        // 2. Paid Plan Logic
        try {
            $currency = Currency::normalize($plan->currency ?? Currency::default());

            // Try to find linked course for metadata
            $courseId = null;
            $linkedCourse = $plan->courses()->first();
            if ($linkedCourse) {
                $courseId = $linkedCourse->id;
            }

            // Create pending payment record
            $payment = \App\Models\Payment::create([
                'user_id' => $user->id,
                'amount' => $plan->price,
                'currency' => $currency,
                'status' => 'pending',
                'payment_method' => $paymentService->gatewayKey(),
                'payment_provider' => $paymentService->gatewayKey(),
                'payment_details' => [
                    'billing_plan_id' => $plan->id,
                    'course_id' => $courseId,
                ],
            ]);

            // Initiate Checkout
            $checkout = $paymentService->createCheckout(
                amount: (float) $plan->price,
                currency: $currency,
                customer: [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                metadata: [
                    'customer_reference' => (string) $payment->id,
                ],
                callbackUrl: route('payments.callback'),
                errorUrl: route('payments.error')
            );

            if (!empty($checkout['transaction_id'])) {
                $payment->update(['transaction_id' => (string) $checkout['transaction_id']]);
            }

            return response()->json([
                'message' => 'Payment initiated',
                'payment_url' => $checkout['payment_url'] ?? null,
                'payment_id' => $payment->id,
                'status' => 'payment_initiated',
            ]);
        } catch (\Throwable $e) {
            Log::error('Entitlement Payment Initiation Failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enroll in a free course directly.
     */
    public function enroll(Course $course, EntitlementService $entitlementService): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check if user already has active access
        $entitlements = $user->entitlements()
            ->active()
            ->whereHas('billingPlan.courses', function ($query) use ($course) {
                $query->where('courses.id', $course->id);
            })
            ->get();

        $hasAccess = $entitlements->filter(function ($entitlement) {
            return $entitlement->isActive();
        })->isNotEmpty();

        if ($hasAccess) {
            return response()->json(['message' => 'You already have active access to this course'], 422);
        }

        // Check if course has a free plan
        $freePlan = BillingPlan::where('billing_type', 'free')
            ->where('is_active', true)
            ->whereHas('courses', function ($query) use ($course) {
                $query->where('courses.id', $course->id);
            })
            ->first();

        if (!$freePlan) {
            return response()->json(['message' => 'This course is not free'], 403);
        }

        try {
            // Grant entitlement (Service handles enrollment creation)
            $entitlement = $entitlementService->grantEntitlement($user, $freePlan);

            return response()->json([
                'message' => 'Successfully enrolled',
                'entitlement' => $entitlement
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Enrollment failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancel an entitlement.
     */
    public function cancel(UserEntitlement $entitlement, Request $request): JsonResponse
    {
        if ($entitlement->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to entitlement');
        }

        // Logic to cancel recurring billing at gateway would go here.
        // For now, we just acknowledge the request.
        // Entitlement status remains active until expiration.

        return response()->json([
            'message' => 'Entitlement cancellation request received.',
            'entitlement' => $entitlement,
        ]);
    }

    /**
     * Renew an entitlement.
     */
    public function renew(
        UserEntitlement $entitlement,
        Request $request,
        EntitlementService $entitlementService,
        PaymentServiceInterface $paymentService
    ): JsonResponse {
        if ($entitlement->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to entitlement');
        }

        $plan = $entitlement->billingPlan;

        if (!$plan || !$plan->is_active) {
            return response()->json([
                'message' => 'The billing plan for this entitlement is no longer available.',
            ], 422);
        }

        // Check if the plan is actually renewable (recurring or expired)
        if ($entitlement->isActive() && !$entitlement->ends_at?->isPast()) {
            // It's already active and not yet in grace period/expired
            // For now, we allow "early renewal" or "extension" if user wants
        }

        // Reuse the checkout logic by calling PaymentGatewayController
        $gatewayController = app(\App\Http\Controllers\PaymentGatewayController::class);
        $request->merge([
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'plan_id' => $plan->id,
            'course_id' => $plan->courses()->first()?->id,
        ]);

        return $gatewayController->checkout($request);
    }

    /**
     * Calculate upgrade price.
     */
    public function calculateUpgrade(
        UserEntitlement $entitlement,
        BillingPlan $newPlan
    ): JsonResponse {
        if ($entitlement->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to entitlement');
        }

        // 1. Calculate remaining days on current plan
        $now = now();
        $endsAt = $entitlement->ends_at;

        if (!$endsAt || $endsAt->isPast()) {
            $remainingDays = 0;
        } else {
            $remainingDays = $now->diffInDays($endsAt);
        }

        // 2. Calculate daily price of the NEW plan
        // (Assuming 30 days for monthly, 365 for yearly if not specified)
        $durationDays = $newPlan->access_duration_days;
        if (!$durationDays) {
            if ($newPlan->billing_interval === 'month') $durationDays = 30;
            elseif ($newPlan->billing_interval === 'year') $durationDays = 365;
            elseif ($newPlan->billing_interval === 'week') $durationDays = 7;
            elseif ($newPlan->billing_interval === 'day') $durationDays = 1;
            else $durationDays = 30; // Fallback
        }

        $dailyPrice = (float) $newPlan->price / $durationDays;

        // 3. Upgrade price = remaining days * daily price of new plan
        $upgradePrice = round($remainingDays * $dailyPrice, 2);

        return response()->json([
            'current_plan' => $entitlement->billingPlan->name,
            'new_plan' => $newPlan->name,
            'remaining_days' => $remainingDays,
            'daily_price' => round($dailyPrice, 2),
            'upgrade_price' => $upgradePrice,
            'currency' => $newPlan->currency,
        ]);
    }

    /**
     * Upgrade an entitlement.
     */
    public function upgrade(
        UserEntitlement $entitlement,
        BillingPlan $newPlan,
        Request $request,
        EntitlementService $entitlementService,
        PaymentServiceInterface $paymentService
    ): JsonResponse {
        if ($entitlement->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to entitlement');
        }

        // 1. Get the upgrade price
        $calculation = $this->calculateUpgrade($entitlement, $newPlan)->getData();
        $upgradePrice = $calculation->upgrade_price;

        // 2. Handle free upgrade (shouldn't happen with paid plans, but for safety)
        if ($upgradePrice <= 0) {
            $user = Auth::user();
            $newEntitlement = $entitlementService->grantEntitlement($user, $newPlan);

            // Mark old one as canceled/superseded
            $entitlement->update(['status' => UserEntitlement::STATUS_CANCELED]);

            return response()->json([
                'message' => 'Upgraded successfully',
                'entitlement' => $newEntitlement,
            ]);
        }

        // 3. Initiate payment for the upgrade price via PaymentGatewayController
        $gatewayController = app(\App\Http\Controllers\PaymentGatewayController::class);
        $request->merge([
            'amount' => $upgradePrice,
            'currency' => $newPlan->currency,
            'plan_id' => $newPlan->id,
            'course_id' => $newPlan->courses()->first()?->id,
        ]);

        // We need to pass the upgrade_from_entitlement_id to the payment_details
        // The PaymentGatewayController::checkout currently doesn't support extra details
        // Let's modify checkout to accept them or just handle it here since we need the specific meta

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currency = Currency::normalize($newPlan->currency ?? Currency::default());

        $payment = \App\Models\Payment::create([
            'user_id' => $user->id,
            'amount' => $upgradePrice,
            'currency' => $currency,
            'status' => 'pending',
            'payment_method' => $paymentService->gatewayKey(),
            'payment_provider' => $paymentService->gatewayKey(),
            'payment_details' => [
                'billing_plan_id' => $newPlan->id,
                'upgrade_from_entitlement_id' => $entitlement->id,
                'payment_method_id' => $request->payment_method_id,
            ],
        ]);

        $checkout = $paymentService->createCheckout(
            amount: (float) $upgradePrice,
            currency: $currency,
            customer: [
                'name' => $user->name,
                'email' => $user->email,
            ],
            metadata: [
                'customer_reference' => (string) $payment->id,
                'type' => 'upgrade',
            ],
            callbackUrl: route('payments.callback'),
            errorUrl: route('payments.error'),
            paymentMethodId: $request->payment_method_id
        );

        if (!empty($checkout['transaction_id'])) {
            $payment->update(['transaction_id' => (string) $checkout['transaction_id']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Upgrade payment initiated',
            'payment_url' => $checkout['payment_url'] ?? null,
            'payment_id' => $payment->id,
            'status' => 'payment_initiated',
        ]);
    }

    /**
     * Get free content for a course.
     * @deprecated Legacy method. Returns empty lists as free content logic is removed.
     */
    public function getFreeCourseContent(Course $course): JsonResponse
    {
        return response()->json([
            'free_levels' => [],
            'free_lessons_in_paid_levels' => [],
        ]);
    }

    /**
     * Get available billing plans for a course.
     */
    public function getAvailablePlans(Course $course): JsonResponse
    {
        $plans = BillingPlan::whereHas('courses', function ($query) use ($course) {
            $query->where('courses.id', $course->id);
        })
            ->with(['planFeatures.feature'])
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $activeEntitlement = null;

        if ($user) {
            $activeEntitlement = $user->entitlements()
                ->active()
                ->whereHas('billingPlan.courses', function ($query) use ($course) {
                    $query->where('courses.id', $course->id);
                })
                ->with('billingPlan')
                ->first();
        }

        return response()->json([
            'plans' => EntitlementPlanResource::collection($plans)->resolve(),
            'active_entitlement' => $activeEntitlement ? (new \App\Http\Resources\UserEntitlementResource($activeEntitlement))->resolve() : null,
        ]);
    }

    /**
     * Check if a user has access to a specific level.
     */
    public function checkLevelAccess(Level $level): JsonResponse
    {
        $user = Auth::user();
        $hasAccess = $level->isAccessibleToUser($user);

        return response()->json([
            'has_access' => $hasAccess,
        ]);
    }

    /**
     * Check if a user has access to a specific lesson.
     */
    public function checkLessonAccess(Lesson $lesson): JsonResponse
    {
        $user = Auth::user();
        $hasAccess = $lesson->isAccessibleToUser($user);

        return response()->json([
            'has_access' => $hasAccess,
        ]);
    }
}
