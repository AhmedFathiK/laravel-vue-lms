<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BillingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'billing_type',
        'billing_interval',
        'access_type',
        'access_duration_days',
        'is_active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'access_duration_days' => 'integer',
    ];

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function features()
    {
        return $this->hasManyThrough(Feature::class, PlanFeature::class, 'billing_plan_id', 'id', 'id', 'feature_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'billing_plan_course');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(UserEntitlement::class);
    }
}
