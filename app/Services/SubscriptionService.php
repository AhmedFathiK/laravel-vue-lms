<?php

namespace App\Services;

use App\Events\SubscriptionCreated;
use App\Exceptions\DuplicateSubscriptionException;
use App\Exceptions\PaymentRequiredException;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Payment\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Process a successful payment: Create subscription and receipt.
     * This method is idempotent: safe to call multiple times (handles duplicates).
     *
     * @param Payment $payment
     * @return void
     * @throws \Throwable
     */
    public function processSuccessfulPayment(Payment $payment): void
    {
        $existingDetails = $payment->payment_details ?? [];
        if (!isset($existingDetails['plan_id'])) {
            return;
        }

        $plan = SubscriptionPlan::find($existingDetails['plan_id']);
        if (!$plan || !$payment->user_id) {
            return;
        }

        $user = User::find($payment->user_id);
        if (!$user) {
            return;
        }

        DB::transaction(function () use ($user, $plan, $payment) {
            try {
                // We use force=false to respect idempotency (checks if active subscription exists)
                // If it exists, it throws DuplicateSubscriptionException
                $this->createSubscription($user, $plan, $payment);
            } catch (DuplicateSubscriptionException $e) {
                // Subscription already exists, which is fine (idempotency).
                // We just log it and proceed to ensure Receipt exists.
                Log::info("Subscription already exists for payment {$payment->id} during processing: " . $e->getMessage());
            }

            // Create Receipt if not exists for this payment
            if (!Receipt::where('payment_id', $payment->id)->exists()) {
                Receipt::create([
                    'user_id' => $payment->user_id,
                    'payment_id' => $payment->id,
                    'receipt_number' => Receipt::generateUniqueReceiptNumber(),
                    'item_type' => 'subscription_plan',
                    'item_id' => $plan->id,
                    'item_name' => $plan->name,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);
            }
        });
    }

    /**
     * Create a new subscription and enrollment for the user.
     *
     * @param User $user
     * @param SubscriptionPlan $plan
     * @param Payment|null $payment
     * @param bool $force If true, bypasses duplicate check and payment requirement check
     * @return UserSubscription
     * @throws DuplicateSubscriptionException
     * @throws PaymentRequiredException
     */
    public function createSubscription(User $user, SubscriptionPlan $plan, ?Payment $payment = null, bool $force = false): UserSubscription
    {
        return DB::transaction(function () use ($user, $plan, $payment, $force) {
            // 1. Idempotency Check
            $existingSubscription = UserSubscription::where('user_id', $user->id)
                ->whereIn('status', [UserSubscription::STATUS_ACTIVE, UserSubscription::STATUS_PAST_DUE])
                ->whereHas('plan', function ($query) use ($plan) {
                    $query->where('course_id', $plan->course_id);
                })
                ->lockForUpdate() // Prevent race conditions
                ->first();

            if ($existingSubscription && !$force) {
                throw new DuplicateSubscriptionException("User already has an active subscription for this course.");
            }

            // 2. Payment Validation
            // If plan is paid (>0), not free, and payment is missing, throw exception.
            // Unless forced (admin override).
            if (!$plan->is_free && $plan->price > 0 && !$payment && !$force) {
                throw new PaymentRequiredException("This subscription plan requires a valid payment.");
            }

            // 3. Create Subscription
            $subscription = new UserSubscription();
            $subscription->user_id = $user->id;
            $subscription->subscription_plan_id = $plan->id;
            $subscription->payment_id = $payment?->id;
            $subscription->starts_at = now();
            
            // Determine Status based on Payment
            if ($plan->is_free) {
                $subscription->status = UserSubscription::STATUS_ACTIVE;
            } elseif ($payment && $payment->status === 'completed') {
                $subscription->status = UserSubscription::STATUS_ACTIVE;
            } elseif ($payment && $payment->status !== 'completed') {
                $subscription->status = UserSubscription::STATUS_PENDING;
            } else {
                // Fallback for manual overrides without payment objects (should be rare/avoided)
                $subscription->status = UserSubscription::STATUS_ACTIVE;
            }

            // Set end date based on plan type
            if ($plan->plan_type === 'recurring') {
                $subscription->auto_renew = true;
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

            // 4. Create/Update Course Enrollment
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

            // 5. Fire Event (Sends Email + PDF Receipt if applicable)
            event(new SubscriptionCreated($subscription));

            return $subscription;
        });
    }

    /**
     * Create a manual subscription with an auto-generated zero/manual payment and receipt.
     * Useful for Admin overrides.
     *
     * @param User $user
     * @param SubscriptionPlan $plan
     * @param string|null $notes
     * @return UserSubscription
     */
    public function createManualSubscription(User $user, SubscriptionPlan $plan, ?string $notes = null): UserSubscription
    {
        return DB::transaction(function () use ($user, $plan, $notes) {
            $currency = Currency::default();
            
            // Create Manual Payment
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_method' => 'manual',
                'amount' => $plan->price,
                'currency' => $plan->currency ?? $currency,
                'status' => 'completed',
                'transaction_id' => 'MANUAL-' . strtoupper(uniqid()),
                'payment_provider' => 'Manual',
                'payment_details' => [
                    'notes' => $notes ?? 'Manual subscription created by admin',
                    'created_at' => now()->toIso8601String(),
                ],
            ]);

            // Create Receipt
            $course = $plan->course;
            Receipt::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'receipt_number' => Receipt::generateUniqueReceiptNumber(),
                'item_type' => 'subscription_plan',
                'item_id' => $plan->id,
                'item_name' => ($course ? $course->title . ' - ' : '') . $plan->name,
                'amount' => $plan->price,
                'currency' => $plan->currency ?? $currency,
            ]);

            // Call createSubscription with the manual payment
            // We pass force=false to respect idempotency. 
            // If the user already has a subscription, this will throw DuplicateSubscriptionException,
            // rolling back the Payment and Receipt creation.
            return $this->createSubscription($user, $plan, $payment, false);
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

    /**
     * Revoke a subscription associated with a refunded/failed payment.
     *
     * @param Payment $payment
     * @param string $reason
     * @return void
     */
    public function revokeSubscription(Payment $payment, string $reason = 'Payment Refunded'): void
    {
        $subscription = UserSubscription::where('payment_id', $payment->id)->first();

        if (!$subscription) {
            Log::warning("Attempted to revoke subscription for payment {$payment->id} but none found.");
            return;
        }

        if ($subscription->status === UserSubscription::STATUS_CANCELED) {
            return;
        }

        $subscription->update([
            'status' => UserSubscription::STATUS_CANCELED,
            'cancellation_reason' => $reason,
            'ends_at' => now(), // Revoke access immediately
            'auto_renew' => false,
        ]);

        Log::info("Subscription {$subscription->id} revoked. Reason: {$reason}");
    }
}
