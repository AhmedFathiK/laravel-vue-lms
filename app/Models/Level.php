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
        return $user->subscriptions()
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
                Lesson::$cascadingDelete = self::$cascadingDelete;

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
