<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Lesson extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'level_id',
        'title',
        'description',
        'sort_order',
        'status',
        'video_url',
        'video_type',
        'reshow_incorrect_slides',
        'reshow_count',
        'require_correct_answers',
        'thumbnail',
        'is_free',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'sort_order' => 'integer',
        'reshow_incorrect_slides' => 'boolean',
        'reshow_count' => 'integer',
        'require_correct_answers' => 'boolean',
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

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function studiedBy()
    {
        return $this->hasMany(UserStudiedLesson::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class)->orderBy('sort_order');
    }

    /**
     * Check if this lesson is accessible to a user based on their entitlements.
     */
    public function isAccessibleToUser(User $user): bool
    {
        // Ensure content is published
        if ($this->status !== 'published' || $this->level->status !== 'published') {
            return false;
        }

        // Check lesson is free
        if ($this->is_free && $user->hasFeature('content.free.access', 'App\Models\Course', $this->level->course_id)) {
            return true;
        }

        // 2. Check if parent Level is free
        if ($this->level->is_free && $user->hasFeature('content.free.access', 'App\Models\Course', $this->level->course_id)) {
            return true;
        }

        // 3. Check paid access
        if ($user->hasFeature('content.paid.access', 'App\Models\Course', $this->level->course_id)) {
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

        // When a lesson is soft deleted, also soft delete all related slides and progress
        static::deleting(function ($lesson) {
            if (!$lesson->isForceDeleting()) {
                // Propagate cascading flag to slides
                Slide::$cascadingDelete = true;

                $lesson->slides()->each(function ($slide) {
                    $slide->delete();
                });

                // Also delete user progress records
                $lesson->studiedBy()->delete();

                // Reset the slide flag if we're not in a cascading delete
                if (!self::$cascadingDelete) {
                    Slide::$cascadingDelete = false;
                }
            }
        });
    }
}
