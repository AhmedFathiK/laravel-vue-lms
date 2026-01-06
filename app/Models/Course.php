<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Course extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'thumbnail',
        'is_featured',
        'course_category_id',
        'main_locale',
        'leaderboard_reset_frequency',
        'prerequisites',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_free' => 'boolean',
        'prerequisites' => 'array',
    ];

    public function toArray()
    {
        $attributes = parent::toArray();

        foreach ($this->translatable as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = $this->getTranslation($field, app()->getLocale());
            }
        }

        // Append calculated attributes
        $attributes['is_free'] = $this->is_free;
        $attributes['subscription_type'] = $this->subscription_type;

        return $attributes;
    }

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

    public function conceptCategories(): HasMany
    {
        return $this->hasMany(ConceptCategory::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
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

    /**
     * Flag to indicate cascading deletion from parent model
     */
    public static $cascadingDelete = false;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When a course is soft deleted, also soft delete all related levels
        static::deleting(function ($course) {
            if (!$course->isForceDeleting()) {
                // Set cascading flag for child models
                Level::$cascadingDelete = true;

                // Cascade soft delete to all related models
                $course->levels()->each(function ($level) {
                    $level->delete();
                });

                $course->terms()->each(function ($term) {
                    $term->delete();
                });

                $course->concepts()->each(function ($concept) {
                    $concept->delete();
                });

                // Reset the flag
                Level::$cascadingDelete = false;
            }
        });
    }
}
