<?php

namespace App\Services;

use App\Models\BillingPlan;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserCapability;
use App\Models\Payment;
use App\Models\CourseEnrollment;
use App\Models\Receipt;
use App\Events\EntitlementCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\PaymentToken;
use App\Services\Payments\PaymentServiceInterface;

class EntitlementService
{
    /**
     * Attempt to auto-renew an entitlement using a saved token.
     */
    public function attemptAutoRenew(UserEntitlement $entitlement): bool
    {
        // 1. Check if auto-renew is enabled and entitlement is past_due or expiring soon
        if (!$entitlement->auto_renew) {
            return false;
        }

        $user = $entitlement->user;
        $plan = $entitlement->billingPlan;

        if (!$user || !$plan || $plan->price <= 0) {
            return false;
        }

        // 2. Find a saved token for the last payment gateway used
        $lastPayment = $entitlement->payment;
        $gateway = $lastPayment?->payment_method;

        if (!$gateway) {
            // Fallback: try to find any default token for the user
            $token = $user->paymentTokens()->where('is_default', true)->first();
        } else {
            $token = $user->paymentTokens()
                ->where('gateway', $gateway)
                ->where('is_default', true)
                ->first();
        }

        if (!$token) {
            Log::info("Auto-renew failed for entitlement {$entitlement->id}: No saved token found for user {$user->id}");
            return false;
        }

        // 3. Resolve the payment gateway service
        try {
            /** @var PaymentServiceInterface $paymentService */
            $paymentService = app($token->gateway === 'paymob' ? \App\Services\Payment\PaymobService::class : \App\Services\Payment\MyFatoorahService::class);

            // 4. Create a pending payment record for the renewal
            $newPayment = Payment::create([
                'user_id' => $user->id,
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'USD',
                'status' => 'pending',
                'payment_method' => $token->gateway,
                'payment_provider' => $token->gateway,
                'payment_details' => [
                    'billing_plan_id' => $plan->id,
                    'renew_entitlement_id' => $entitlement->id,
                    'is_auto_renew' => true,
                ],
            ]);

            // 5. Charge the token
            $charge = $paymentService->chargeToken(
                token: $token->token,
                amount: (float) $plan->price,
                currency: $plan->currency ?? 'USD',
                customer: [
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                ],
                metadata: [
                    'customer_reference' => (string) $newPayment->id,
                ]
            );

            // 6. Update payment status
            $newPayment->update([
                'status' => $charge['status'] === 'paid' ? 'completed' : 'failed',
                'transaction_id' => $charge['transaction_id'] ?? null,
                'payment_details' => array_merge($newPayment->payment_details, [
                    'gateway_data' => $charge['gateway_data'] ?? [],
                ]),
            ]);

            if ($charge['status'] === 'paid') {
                // 7. Renew the entitlement
                $this->renewEntitlement($entitlement, $plan, $newPayment);

                // Update token last used at
                $token->update(['last_used_at' => now()]);

                Log::info("Auto-renew success for entitlement {$entitlement->id} via {$token->gateway}");
                return true;
            } else {
                Log::warning("Auto-renew failed for entitlement {$entitlement->id}: Charge status is {$charge['status']}");
                return false;
            }
        } catch (\Throwable $e) {
            Log::error("Auto-renew error for entitlement {$entitlement->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alias for grantEntitlement to satisfy tests.
     */
    public function createEntitlement(User $user, BillingPlan $plan, ?Payment $payment = null): UserEntitlement
    {
        return $this->grantEntitlement($user, $plan, $payment);
    }

    /**
     * Grant an entitlement to a user based on a billing plan.
     */
    public function grantEntitlement(User $user, BillingPlan $plan, ?Payment $payment = null): UserEntitlement
    {
        return DB::transaction(function () use ($user, $plan, $payment) {
            // 1. Calculate End Date
            $startsAt = now();
            $endsAt = null;

            if ($plan->access_type === 'limited') {
                $endsAt = $startsAt->copy()->addDays($plan->access_duration_days);
            } elseif ($plan->access_type === 'while_active') {
                // For recurring, "while_active" usually means "until next billing cycle + grace".
                // But Entitlement strictly defines ACCESS end date.
                // Billing interval determines when we CHARGE again.
                // If billing is monthly, we grant 1 month access.
                if ($plan->billing_interval === 'month') {
                    $endsAt = $startsAt->copy()->addMonth();
                } elseif ($plan->billing_interval === 'year') {
                    $endsAt = $startsAt->copy()->addYear();
                } else {
                    $endsAt = $startsAt->copy()->addDays($plan->access_duration_days ?? 30);
                }
            }
            // lifetime = null ends_at

            // 2. Create Entitlement
            $entitlement = UserEntitlement::create([
                'user_id' => $user->id,
                'billing_plan_id' => $plan->id,
                'payment_id' => $payment?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
                'auto_renew' => $plan->billing_type === 'recurring',
            ]);

            // 3. Snapshot Capabilities
            // 3.1 Course Access (Legacy/Progress support)
            foreach ($plan->courses as $course) {
                CourseEnrollment::updateOrCreate([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ], [
                    'user_entitlement_id' => $entitlement->id,
                    'enrolled_at' => now(),
                    'last_accessed_at' => now(),
                ]);
            }

            // 3.2 Plan Features
            foreach ($plan->planFeatures as $pf) {
                // Skip if feature definition is missing
                if (!$pf->feature) {
                    Log::warning("PlanFeature {$pf->id} has missing Feature definition. Skipping capability creation.");
                    continue;
                }

                UserCapability::create([
                    'user_entitlement_id' => $entitlement->id,
                    'feature_code' => $pf->feature->code,
                    'scope_type' => $pf->scope_type,
                    'scope_id' => $pf->scope_id,
                    'value' => $pf->value,
                ]);
            }

            // 5. Create Receipt
            if ($payment) {
                Receipt::create([
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                    'receipt_number' => 'REC-' . strtoupper(bin2hex(random_bytes(4))),
                    'item_type' => 'billing_plan',
                    'item_id' => $plan->id,
                    'item_name' => $plan->name,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);
            }

            // 6. Dispatch Event
            event(new EntitlementCreated($entitlement));

            return $entitlement;
        });
    }

    /**
     * Renew an existing entitlement by extending its expiry date.
     */
    public function renewEntitlement(UserEntitlement $entitlement, BillingPlan $plan, ?Payment $payment = null): UserEntitlement
    {
        return DB::transaction(function () use ($entitlement, $plan, $payment) {
            // 1. Determine base date for extension
            // If currently active and ends_at is in the future, extend from ends_at.
            // If expired or in grace period, extend from now().
            $baseDate = ($entitlement->ends_at && $entitlement->ends_at->isFuture())
                ? $entitlement->ends_at
                : now();

            $newEndsAt = null;

            if ($plan->access_type === 'limited') {
                $newEndsAt = $baseDate->copy()->addDays($plan->access_duration_days);
            } elseif ($plan->access_type === 'while_active') {
                if ($plan->billing_interval === 'month') {
                    $newEndsAt = $baseDate->copy()->addMonth();
                } elseif ($plan->billing_interval === 'year') {
                    $newEndsAt = $baseDate->copy()->addYear();
                } else {
                    $newEndsAt = $baseDate->copy()->addDays($plan->access_duration_days ?? 30);
                }
            }

            // 2. Update Entitlement
            $entitlement->update([
                'payment_id' => $payment?->id ?? $entitlement->payment_id,
                'ends_at' => $newEndsAt,
                'status' => 'active', // Reset status to active if it was expired/past_due
                'auto_renew' => $plan->billing_type === 'recurring',
            ]);

            // 3. Update Enrollment if needed (ensure it points to this entitlement)
            foreach ($plan->courses as $course) {
                CourseEnrollment::updateOrCreate([
                    'user_id' => $entitlement->user_id,
                    'course_id' => $course->id,
                ], [
                    'user_entitlement_id' => $entitlement->id,
                ]);
            }

            // 4. Create Receipt
            if ($payment) {
                Receipt::create([
                    'user_id' => $entitlement->user_id,
                    'payment_id' => $payment->id,
                    'receipt_number' => 'REC-' . strtoupper(bin2hex(random_bytes(4))),
                    'item_type' => 'billing_plan',
                    'item_id' => $plan->id,
                    'item_name' => $plan->name . ' (Renewal)',
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);
            }

            return $entitlement;
        });
    }

    /**
     * Process a successful payment: Create entitlement.
     */
    public function processSuccessfulPayment(Payment $payment): void
    {
        $existingDetails = $payment->payment_details ?? [];
        $planId = $existingDetails['billing_plan_id'] ?? $existingDetails['plan_id'] ?? null;
        $upgradeFromId = $existingDetails['upgrade_from_entitlement_id'] ?? null;
        $renewEntitlementId = $existingDetails['renew_entitlement_id'] ?? null;

        if (!$planId) {
            return;
        }

        $plan = BillingPlan::find($planId);
        if (!$plan || !$payment->user_id) {
            return;
        }

        $user = User::find($payment->user_id);
        if (!$user) {
            return;
        }

        // 1. Handle Renewal Flow (Update existing)
        if ($renewEntitlementId) {
            $entitlement = UserEntitlement::find($renewEntitlementId);
            if ($entitlement && $entitlement->user_id === $user->id) {
                $this->renewEntitlement($entitlement, $plan, $payment);
                Log::info("Entitlement {$renewEntitlementId} renewed for user {$user->id}");
                return;
            }
        }

        // 2. Handle Upgrade Flow (Cancel old, create new)
        if ($upgradeFromId) {
            $oldEntitlement = UserEntitlement::find($upgradeFromId);
            if ($oldEntitlement && $oldEntitlement->user_id === $user->id) {
                $oldEntitlement->update(['status' => UserEntitlement::STATUS_CANCELED]);
                Log::info("Old entitlement {$upgradeFromId} canceled for upgrade to plan {$planId}");
            }
        }

        // Idempotency: Check if entitlement already exists for this payment
        if (UserEntitlement::where('payment_id', $payment->id)->exists()) {
            Log::info("Entitlement already exists for payment {$payment->id}");
            return;
        }

        // Also check if user already has an active entitlement for this plan
        // This handles cases where the same user might have been granted access manually
        // or through another flow before the payment callback completed.
        // SKIP this check if it's an upgrade, as we WANT to grant the new plan even if old one is still active
        if (
            !$upgradeFromId && UserEntitlement::where('user_id', $user->id)
            ->where('billing_plan_id', $plan->id)
            ->whereIn('status', [UserEntitlement::STATUS_ACTIVE, UserEntitlement::STATUS_PAST_DUE])
            ->exists()
        ) {
            Log::info("User {$user->id} already has an active entitlement for plan {$plan->id}. Skipping creation.");

            // If a payment was made, we still need to create a receipt for it
            if ($payment && !Receipt::where('payment_id', $payment->id)->exists()) {
                Receipt::create([
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                    'receipt_number' => 'REC-' . strtoupper(bin2hex(random_bytes(4))),
                    'item_type' => 'billing_plan',
                    'item_id' => $plan->id,
                    'item_name' => $plan->name,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);
            }

            return;
        }

        $this->grantEntitlement($user, $plan, $payment);
    }

    /**
     * Revoke an entitlement associated with a refunded/failed payment.
     */
    public function revokeEntitlement(Payment $payment, string $reason = 'Payment Refunded'): void
    {
        $entitlement = UserEntitlement::where('payment_id', $payment->id)->first();

        if (!$entitlement) {
            Log::warning("Attempted to revoke entitlement for payment {$payment->id} but none found.");
            return;
        }

        if ($entitlement->status === 'revoked') {
            return;
        }

        $entitlement->update([
            'status' => 'revoked',
            'ends_at' => now(),
        ]);

        Log::info("Entitlement {$entitlement->id} revoked. Reason: {$reason}");
    }

    /**
     * Check if user has access to a specific feature.
     */
    public function hasAccess(User $user, string $featureCode, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        // Special case for course access
        if ($featureCode === 'course.access' && $scopeType === 'App\Models\Course') {
            $entitlements = $user->entitlements()
                ->active()
                ->whereHas('billingPlan.courses', function ($q) use ($scopeId) {
                    $q->where('course_id', $scopeId);
                })
                ->get();

            return $entitlements->filter(function ($entitlement) {
                return $entitlement->isActive();
            })->isNotEmpty();
        }

        // 1. Find active entitlements
        // 2. Join with capabilities
        // 3. Check for match
        return $user->capabilities()
            ->where('feature_code', $featureCode)
            ->where(function ($q) use ($scopeType, $scopeId) {
                $q->whereNull('scope_id') // Global access (e.g. All Courses)
                    ->orWhere(function ($q2) use ($scopeType, $scopeId) {
                        $q2->where('scope_type', $scopeType)
                            ->where('scope_id', $scopeId);
                    });
            })
            ->whereHas('entitlement', function ($q) {
                $q->active(); // Checks dates & status
            })
            ->exists();
    }

    /**
     * Create a manual entitlement with an auto-generated manual payment and receipt.
     */
    public function grantManualEntitlement(User $user, BillingPlan $plan, ?string $notes = null): UserEntitlement
    {
        return DB::transaction(function () use ($user, $plan, $notes) {
            // Create Manual Payment
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_method' => 'manual',
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'USD',
                'status' => 'completed',
                'payment_details' => [
                    'billing_plan_id' => $plan->id,
                    'notes' => $notes ?? 'Manually granted by admin',
                ],
                'transaction_id' => 'MAN-' . strtoupper(bin2hex(random_bytes(4))),
            ]);

            // Grant Entitlement
            return $this->grantEntitlement($user, $plan, $payment);
        });
    }
}
