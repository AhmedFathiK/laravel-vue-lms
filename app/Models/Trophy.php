<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Trophy extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'name',
        'description',
        'icon_url',
        'course_id',
        'trigger_type',
        'trigger_repeat_count',
        'requirements',
        'points',
        'points_threshold',
        'rarity',
        'is_hidden',
        'is_active',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'requirements' => 'json',
        'points' => 'integer',
        'points_threshold' => 'integer',
        'trigger_repeat_count' => 'integer',
        'is_hidden' => 'boolean',
        'is_active' => 'boolean',
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
     * Get the course this trophy belongs to (if any)
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the users who have earned this trophy
     */
    public function userTrophies(): HasMany
    {
        return $this->hasMany(UserTrophy::class);
    }

    /**
     * Scope a query to only include active trophies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include visible trophies
     */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Scope a query to only include trophies for a specific course
     */
    public function scopeForCourse($query, $courseId)
    {
        return $query->where(function ($q) use ($courseId) {
            $q->where('course_id', $courseId)
                ->orWhereNull('course_id'); // Include global trophies
        });
    }
}
