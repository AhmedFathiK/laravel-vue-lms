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
        'user_id', 'billing_plan_id', 'payment_id',
        'starts_at', 'ends_at', 'status', 'auto_renew'
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
        $calculatedGraceDays = ($durationInDays * $percentage) / 100;
        
        // Final grace period is the minimum of calculated percentage and max absolute days
        $effectiveGraceDays = min($calculatedGraceDays, $maxDays);

        return $this->ends_at->copy()->addDays($effectiveGraceDays)->isFuture();
    }
    
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAST_DUE])
            ->where(function($q) {
                $q->whereNull('ends_at')
                  ->orWhere(function($sq) {
                      // We need to use a subquery to calculate the dynamic grace period for each row
                      // Since SQL doesn't easily handle this dynamic calculation without raw queries,
                      // we'll use a conservative approach for the scope (max_days) and rely on 
                      // PHP-level isActive() for precise checks if needed.
                      $maxDays = config('entitlement.grace_period.max_days', 7);
                      $sq->where('ends_at', '>', now()->subDays($maxDays));
                  });
            });
    }
}
