<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Level extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
        'status',
        'is_unlocked',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Flag to indicate cascading deletion from parent model
     */
    public static $cascadingDelete = false;


    public function toArray()
    {
        $attributes = parent::toArray();

        foreach ($this->translatable as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = $this->getTranslation($field, app()->getLocale());
            }
        }

        return $attributes;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Check if this level is accessible to a user based on their entitlements.
     */
    public function isAccessibleToUser(User $user): bool
    {
        // Ensure content is published
        if ($this->status !== 'published' || $this->course->status !== 'published') {
            return false;
        }

        // Check if user has entitlement for this course
        return $user->entitlements()
            ->active()
            ->whereHas('billingPlan.courses', function ($q) {
                $q->where('courses.id', $this->course_id);
            })
            ->exists();

    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When a level is soft deleted, also soft delete all related lessons
        static::deleting(function ($level) {
            if (!$level->isForceDeleting()) {
                // Propagate cascading flag to lessons
                Lesson::$cascadingDelete = true;

                $level->lessons()->each(function ($lesson) {
                    $lesson->delete();
                });

                // Reset the lesson flag if we're not in a cascading delete
                if (!self::$cascadingDelete) {
                    Lesson::$cascadingDelete = false;
                }
            }
        });
    }
}
