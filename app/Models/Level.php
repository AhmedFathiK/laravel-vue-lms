<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\User;
use App\Models\ExamAttempt;

class Level extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
        'status',
        'final_exam_id',
        'is_free',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_free' => 'boolean',
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

    /**
     * Get the exams for this level.
     * Note: We use hasMany even though it's a 1:1 relationship via final_exam_id
     * to maintain compatibility with controller logic that expects a collection.
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'id', 'final_exam_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function finalExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'final_exam_id');
    }

    public function userLevelProgress(): HasMany
    {
        return $this->hasMany(UserLevelProgress::class);
    }

    public function currentUserProgress(): HasOne
    {
        return $this->hasOne(UserLevelProgress::class)
            ->where('user_id', \Illuminate\Support\Facades\Auth::id());
    }

    public function placementAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'placement_outcome_level_id');
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

        // Check if level is free
        if ($this->is_free && $user->hasCapability('content.free.access', 'App\Models\Course', $this->course_id)) {
            return true;
        }

        // Check paid access
        if ($user->hasCapability('content.paid.access', 'App\Models\Course', $this->course_id)) {
            return true;
        }

        return false;
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
                // Pre-production Rule: Remove strict model guards.
                // We allow deletion at the model level; safety checks are now in the controller.

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
