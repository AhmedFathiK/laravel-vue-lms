<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Exceptions\DuplicateSubscriptionException;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Payment\Currency;
use App\Services\Payments\PaymentServiceInterface;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LearnerSubscriptionController extends Controller
{
    /**
     * Display a listing of the user's subscriptions.
     */
    public function index(): JsonResponse
    {
        return response()->json([]);
    }

    /**
     * Get all enrollments for the current user.
     */
    public function myCourses(Request $request): JsonResponse
    {
        $query = CourseEnrollment::where('user_id', Auth::id())
            ->with([
                'course' => function ($query) {
                    $query->where('status', 'published')
                        ->select('id', 'title', 'description', 'thumbnail');
                },
                'userSubscription:id,subscription_plan_id,ends_at,status',
                'userSubscription.subscriptionPlan:id,name'
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
     * Display the specified subscription.
     */
    public function show(UserSubscription $subscription): JsonResponse
    {
        // Ensure the subscription belongs to the authenticated user
        if ($subscription->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to subscription');
        }

        $subscription->load(['plan', 'plan.course', 'payment']);

        return response()->json($subscription);
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(
        Request $request,
        SubscriptionService $subscriptionService,
        PaymentServiceInterface $paymentService
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Check if the plan is active
        if (!$plan->is_active) {
            return response()->json([
                'message' => 'This subscription plan is not available',
            ], 422);
        }

        // 1. Free Plan Logic
        if ($plan->is_free || $plan->price <= 0) {
            try {
                $subscription = $subscriptionService->createSubscription($user, $plan);

                return response()->json([
                    'message' => 'Successfully subscribed to the plan',
                    'subscription' => $subscription->load(['plan', 'plan.course']),
                    'status' => 'enrolled',
                ], 201);
            } catch (DuplicateSubscriptionException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        // 2. Paid Plan Logic
        try {
            $currency = Currency::normalize($plan->currency ?? Currency::default());

            // Create pending payment record
            $payment = \App\Models\Payment::create([
                'user_id' => $user->id,
                'amount' => $plan->price,
                'currency' => $currency,
                'status' => 'pending',
                'payment_method' => $paymentService->gatewayKey(),
                'payment_provider' => $paymentService->gatewayKey(),
                'payment_details' => [
                    'plan_id' => $plan->id,
                    'course_id' => $plan->course_id,
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
                callbackUrl: 'http://127.0.0.1:8000/api/payments/callback1',
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
            Log::error('Subscription Payment Initiation Failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enroll in a free course directly.
     */
    public function enroll(Course $course): JsonResponse
    {
        $user = Auth::user();

        // Check if already enrolled
        if (CourseEnrollment::where('user_id', $user->id)->where('course_id', $course->id)->exists()) {
            return response()->json(['message' => 'Already enrolled'], 422);
        }

        // Check if course is free
        if (!$course->is_free) {
            return response()->json(['message' => 'This course is not free'], 403);
        }

        // Create enrollment
        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
            'last_accessed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Successfully enrolled',
            'enrollment' => $enrollment
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(UserSubscription $subscription, Request $request): JsonResponse
    {
        // Ensure the subscription belongs to the authenticated user
        if ($subscription->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to subscription');
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update subscription
        $subscription->auto_renew = false;
        $subscription->cancellation_reason = $request->cancellation_reason;
        $subscription->save();

        return response()->json([
            'message' => 'Subscription auto-renewal has been canceled',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Renew a subscription.
     */
    public function renew(UserSubscription $subscription): JsonResponse
    {
        // Ensure the subscription belongs to the authenticated user
        if ($subscription->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to subscription');
        }

        // Check if the plan is still active
        if (!$subscription->plan->is_active) {
            return response()->json([
                'message' => 'This subscription plan is no longer available',
            ], 422);
        }

        // Check if the subscription is eligible for renewal
        if ($subscription->plan->plan_type !== 'recurring') {
            return response()->json([
                'message' => 'This subscription type cannot be renewed',
            ], 422);
        }

        // Update subscription
        $subscription->auto_renew = true;
        $subscription->cancellation_reason = null;

        // If the subscription has already ended, restart it
        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            $subscription->starts_at = now();

            // Set the new end date based on the billing cycle
            switch ($subscription->plan->billing_cycle) {
                case 'monthly':
                    $subscription->ends_at = now()->addMonth();
                    break;
                case 'quarterly':
                    $subscription->ends_at = now()->addMonths(3);
                    break;
                case 'yearly':
                    $subscription->ends_at = now()->addYear();
                    break;
                default:
                    $subscription->ends_at = now()->addMonth();
            }
        }
        
        // Always ensure status is active when renewing
        $subscription->status = UserSubscription::STATUS_ACTIVE;

        $subscription->save();

        return response()->json([
            'message' => 'Subscription has been renewed',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Get free content for a course.
     */
    public function getFreeCourseContent(Course $course): JsonResponse
    {
        // Get all free levels
        $freeLevels = $course->levels()
            ->where('is_free', true)
            ->with(['lessons' => function ($query) {
                $query->where('is_free', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Get all free lessons in non-free levels
        $freeLessonsInPaidLevels = $course->levels()
            ->where('is_free', false)
            ->with(['lessons' => function ($query) {
                $query->where('is_free', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($level) {
                return $level->lessons->isNotEmpty();
            });

        return response()->json([
            'is_free_course' => $course->is_free,
            'free_levels' => $freeLevels,
            'free_lessons_in_paid_levels' => $freeLessonsInPaidLevels,
        ]);
    }

    /**
     * Get available subscription plans for a course.
     */
    public function getAvailablePlans(Course $course): JsonResponse
    {
        $plans = $course->subscriptionPlans()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        // Check if the user already has an active subscription for this course
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $activeSubscription = $user->subscriptions()
            ->whereHas('plan', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->where('status', 'active')
            ->with('plan')
            ->first();

        return response()->json([
            'plans' => $plans,
            'active_subscription' => $activeSubscription,
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
            'is_free' => $level->is_free,
            'course_is_free' => $level->course->is_free,
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
            'is_free' => $lesson->is_free,
            'level_is_free' => $lesson->level->is_free,
            'course_is_free' => $lesson->level->course->is_free,
        ]);
    }
}
