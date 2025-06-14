<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Level extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
        'status',
        'is_unlocked',
        'is_free',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'is_free' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /**
     * Get all free lessons in this level.
     */
    public function freeLessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->where('is_free', true)->orderBy('sort_order');
    }

    /**
     * Check if this level is accessible to a user based on their subscription.
     */
    public function isAccessibleToUser(User $user): bool
    {
        // If the level or course is free, it's accessible to everyone
        if ($this->is_free || $this->course->is_free) {
            return true;
        }

        // Check if user has any active subscription for this course
        $hasActiveSubscription = $user->subscriptions()
            ->whereHas('plan', function ($query) {
                $query->where('course_id', $this->course_id)
                    ->where('is_active', true);
            })
            ->where(function ($query) {
                $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });
            })
            ->exists();

        if (!$hasActiveSubscription) {
            return false;
        }

        // Check if the user's subscription plan grants access to this level
        $subscription = $user->subscriptions()
            ->whereHas('plan', function ($query) {
                $query->where('course_id', $this->course_id)
                    ->where('is_active', true);
            })
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with('plan')
            ->first();

        if (!$subscription) {
            return false;
        }

        return $subscription->plan->hasAccessToLevel($this->id);
    }
}
