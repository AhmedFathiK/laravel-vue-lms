<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserEntitlement extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'billing_plan_id',
        'payment_id',
        'starts_at',
        'ends_at',
        'status',
        'auto_renew'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingPlan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(UserCapability::class);
    }

    public function isActive(): bool
    {
        if (!in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PAST_DUE])) {
            return false;
        }

        if ($this->ends_at === null) {
            return true;
        }

        // Calculate grace period based on duration percentage
        $percentage = config('entitlement.grace_period.percentage', 10);
        $maxDays = config('entitlement.grace_period.max_days', 7);

        $durationInDays = $this->starts_at->diffInDays($this->ends_at);

        // Ensure at least 1 day duration to avoid division by zero or tiny grace periods
        $durationInDays = max($durationInDays, 1);

        $calculatedGraceDays = ($durationInDays * $percentage) / 100;

        // Final grace period is the minimum of calculated percentage and max absolute days.
        // We use ceil to ensure at least 1 day grace period for short durations if percentage > 0.
        $effectiveGraceDays = min(ceil($calculatedGraceDays), $maxDays);

        return $this->ends_at->copy()->addDays($effectiveGraceDays)->endOfDay()->isFuture();
    }

    public function scopeActive($query)
    {
        // Use conservative approach for SQL scope to ensure no valid entitlements are missed.
        // Strict percentage-based grace period is enforced in PHP via isActive().
        $maxDays = config('entitlement.grace_period.max_days', 7);

        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAST_DUE])
            ->where(function ($q) use ($maxDays) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now()->subDays($maxDays));
            });
    }
}
