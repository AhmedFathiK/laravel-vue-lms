<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSubscription extends Model
{
    use HasFactory, SoftDeletes;

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
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
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
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
            ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Check if the subscription is a one-time purchase.
     */
    public function isOneTimePurchase(): bool
    {
        return $this->plan->plan_type === 'one-time';
    }

    /**
     * Check if the subscription is recurring.
     */
    public function isRecurring(): bool
    {
        return $this->plan->plan_type === 'recurring';
    }

    /**
     * Check if the subscription is for a free plan.
     */
    public function isFree(): bool
    {
        return $this->plan->is_free;
    }

    /**
     * Check if the subscription grants access to a specific level.
     */
    public function hasAccessToLevel(int $levelId): bool
    {
        // If the plan is free, it grants access to all levels
        if ($this->plan->is_free) {
            return true;
        }

        // Check if the plan has specific level access restrictions
        return $this->plan->hasAccessToLevel($levelId);
    }
}
