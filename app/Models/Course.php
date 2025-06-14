<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Course extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'title',
        'description',
        'status',
        'thumbnail',
        'is_featured',
        'course_category_id',
        'is_free',
        'leaderboard_reset_frequency',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_free' => 'boolean',
    ];

    /**
     * Get the category that owns the course.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /**
     * Get the subscription plans for this course.
     */
    public function subscriptionPlans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('sort_order');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class);
    }

    /**
     * Get all free levels in this course.
     */
    public function freeLevels(): HasMany
    {
        return $this->hasMany(Level::class)->where('is_free', true)->orderBy('sort_order');
    }

    /**
     * Check if the course has any free content.
     */
    public function hasFreeContent(): bool
    {
        if ($this->is_free) {
            return true;
        }

        return $this->levels()->where('is_free', true)->exists() ||
            $this->levels()->whereHas('lessons', function ($query) {
                $query->where('is_free', true);
            })->exists();
    }
}
