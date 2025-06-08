<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Translatable\HasTranslations;

class Concept extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'course_id',
        'title',
        'explanation',
        'type', // grammar, vocabulary, pronunciation, etc.
        'examples',
        'media_url',
        'media_type',
    ];

    public array $translatable = [
        'title',
        'explanation',
        'examples',
    ];

    protected $casts = [
        'examples' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all revision items for this concept.
     */
    public function revisionItems(): MorphMany
    {
        return $this->morphMany(RevisionItem::class, 'revisionable');
    }
}
