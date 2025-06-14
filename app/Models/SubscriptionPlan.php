<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'description',
        'price',
        'currency',
        'billing_cycle',
        'plan_type',
        'is_free',
        'accessible_levels',
        'duration_days',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'duration_days' => 'integer',
        'accessible_levels' => 'array',
    ];

    /**
     * Get the course associated with this plan.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Check if this plan is a recurring subscription.
     */
    public function isRecurring(): bool
    {
        return $this->plan_type === 'recurring';
    }

    /**
     * Check if this plan is a one-time purchase.
     */
    public function isOneTime(): bool
    {
        return $this->plan_type === 'one-time';
    }

    /**
     * Check if this plan grants access to a specific level.
     */
    public function hasAccessToLevel(int $levelId): bool
    {
        if ($this->is_free) {
            return true;
        }

        if (empty($this->accessible_levels)) {
            return true; // If no specific levels are set, assume full access
        }

        return in_array($levelId, $this->accessible_levels);
    }
}
