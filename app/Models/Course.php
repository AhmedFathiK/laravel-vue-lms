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
        'sort_order',
        'course_category_id',
        'price',
        'subscription_type',
        'leaderboard_reset_frequency',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'price' => 'float',
    ];

    /**
     * Get the category that owns the course.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
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
}
