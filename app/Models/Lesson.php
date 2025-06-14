<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Lesson extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'level_id',
        'title',
        'description',
        'sort_order',
        'status',
        'is_free',
        'video_url',
        'reshow_incorrect_slides',
        'reshow_count',
        'require_correct_answers',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_free' => 'boolean',
        'reshow_incorrect_slides' => 'boolean',
        'reshow_count' => 'integer',
        'require_correct_answers' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function slides(): HasMany
    {
        return $this->hasMany(Slide::class)->orderBy('sort_order');
    }

    /**
     * Check if this lesson is accessible to a user based on their subscription.
     */
    public function isAccessibleToUser(User $user): bool
    {
        // If the lesson is free, it's accessible to everyone
        if ($this->is_free) {
            return true;
        }

        // If the level or course is free, the lesson is accessible
        if ($this->level->is_free || $this->level->course->is_free) {
            return true;
        }

        // Otherwise, check level access which handles subscription checks
        return $this->level->isAccessibleToUser($user);
    }
}
