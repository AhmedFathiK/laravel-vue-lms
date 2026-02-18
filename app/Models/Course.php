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
        'final_exam_id',
        'placement_exam_id',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
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
        $attributes['entitlement_type'] = $this->entitlement_type;

        return $attributes;
    }

    /**
     * Get the entitlement type (one-time or recurring) based on billing plans.
     */
    protected function entitlementType(): Attribute
    {
        return Attribute::make(
            get: function () {
                $hasRecurring = $this->billingPlans()->where('billing_type', 'recurring')->exists();
                return $hasRecurring ? 'recurring' : 'one-time';
            }
        );
    }

    /**
     * Get the category that owns the course.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /**
     * Get the plan features scoped to this course.
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'scope_id')->where('scope_type', 'App\Models\Course');
    }

    /**
     * Get the billing plans associated with this course.
     */
    public function billingPlans(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BillingPlan::class, 'billing_plan_course');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class)->orderBy('sort_order');
    }

    public function lessons(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Level::class);
    }

    public function finalExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'final_exam_id');
    }

    public function placementExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'placement_exam_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
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

    public function questionContexts(): HasMany
    {
        return $this->hasMany(QuestionContext::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
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
