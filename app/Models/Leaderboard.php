<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Leaderboard extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'course_id',
        'reset_frequency',
        'is_active',
        'keep_history',
        'max_entries',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'keep_history' => 'boolean',
        'max_entries' => 'integer',
    ];

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
     * Get the course this leaderboard belongs to (if any)
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the entries for this leaderboard
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /**
     * Scope a query to only include active leaderboards
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include leaderboards for a specific course
     */
    public function scopeForCourse($query, $courseId)
    {
        return $query->where(function ($q) use ($courseId) {
            $q->where('course_id', $courseId)
                ->orWhereNull('course_id'); // Include global leaderboards
        });
    }
}
