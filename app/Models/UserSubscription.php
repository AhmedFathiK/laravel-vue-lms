<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSubscription extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'payment_id',
        'starts_at',
        'ends_at',
        'status',
        'auto_renew',
        'cancellation_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    /**
     * Get the user who owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get the subscription plan (legacy alias).
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->plan();
    }

    /**
     * Get the payment associated with this subscription.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Check if the subscription is active.
     * Checks status and dynamically verifies grace period expiration.
     * 
     * CONTRACT: All subscription access decisions MUST go through this method.
     */
    public function isActive(): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            return true;
        }

        if ($this->status === self::STATUS_PAST_DUE) {
            // Calculate dynamic grace period expiration
            if (!$this->ends_at) {
                return true; // No end date = lifetime access
            }

            $gracePercentage = config('subscription.grace_period.percentage', 10);
            $maxGraceDays = config('subscription.grace_period.max_days', 7);

            $startsAt = $this->starts_at ?? $this->created_at;
            $durationInDays = $startsAt->diffInDays($this->ends_at);
            
            $calculatedGraceDays = round($durationInDays * ($gracePercentage / 100));
            $graceDays = min($calculatedGraceDays, $maxGraceDays);

            // Access is allowed only if we are still within the grace period
            return $this->ends_at->copy()->addDays($graceDays)->isFuture();
        }

        return false;
    }

    /**
     * Scope a query to only include active subscriptions.
     * 
     * WARNING: This scope is for LISTING PURPOSES ONLY.
     * It does NOT perform dynamic grace period calculation.
     * NEVER use this for access control decisions. Use isActive() instead.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAST_DUE]);
    }

    /**
     * Check if the subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Check if the subscription has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the subscription is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    /**
     * Check if the subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if the subscription is a one-time purchase.
     */
    public function isOneTimePurchase(): bool
    {
        return $this->subscriptionPlan->plan_type === 'one-time';
    }

    /**
     * Check if the subscription is recurring.
     */
    public function isRecurring(): bool
    {
        return $this->subscriptionPlan->plan_type === 'recurring';
    }

    /**
     * Check if the subscription is for a free plan.
     */
    public function isFree(): bool
    {
        return $this->subscriptionPlan->is_free;
    }

    /**
     * Check if the subscription grants access to a specific level.
     */
    public function hasAccessToLevel(int $levelId): bool
    {
        return true;
    }
}
