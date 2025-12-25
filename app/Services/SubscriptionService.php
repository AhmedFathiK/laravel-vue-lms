<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a new subscription and enrollment for the user.
     */
    public function createSubscription(User $user, SubscriptionPlan $plan, ?Payment $payment = null): UserSubscription
    {
        return DB::transaction(function () use ($user, $plan, $payment) {
            $subscription = new UserSubscription();
            $subscription->user_id = $user->id;
            $subscription->subscription_plan_id = $plan->id;
            $subscription->payment_id = $payment?->id;
            $subscription->starts_at = now();
            $subscription->status = 'active';

            // Set end date based on plan type
            if ($plan->plan_type === 'recurring') {
                // For recurring plans, set auto_renew to true
                $subscription->auto_renew = true;

                // Set the end date based on the billing cycle
                switch ($plan->billing_cycle) {
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
            } elseif ($plan->plan_type === 'one-time') {
                // For one-time purchases, no end date (permanent access)
                $subscription->auto_renew = false;
                $subscription->ends_at = null;
            } elseif ($plan->plan_type === 'free') {
                // For free plans, no end date
                $subscription->auto_renew = false;
                $subscription->ends_at = null;
            } elseif ($plan->duration_days) {
                // Fallback for custom duration
                $subscription->auto_renew = false;
                $subscription->ends_at = now()->addDays($plan->duration_days);
            } else {
                // Default fallback
                $subscription->auto_renew = false;
                $subscription->ends_at = null;
            }

            $subscription->save();

            // Ensure course enrollment exists and is linked to this subscription
            $enrollment = CourseEnrollment::firstOrNew([
                'user_id' => $user->id,
                'course_id' => $plan->course_id,
            ]);

            if (!$enrollment->exists) {
                $enrollment->enrolled_at = now();
            }

            $enrollment->last_accessed_at = now();
            $enrollment->user_subscription_id = $subscription->id;
            $enrollment->save();

            return $subscription;
        });
    }
    
    /**
     * Backward compatibility wrapper if needed.
     * @deprecated Use createSubscription instead.
     */
    public function create(User $user, SubscriptionPlan $plan, ?int $paymentId = null): UserSubscription
    {
        $payment = $paymentId ? Payment::find($paymentId) : null;
        return $this->createSubscription($user, $plan, $payment);
    }
}
