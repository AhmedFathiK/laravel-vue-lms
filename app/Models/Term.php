<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Translatable\HasTranslations;

class Term extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'course_id',
        'term',
        'definition',
        'translation',
        'media_url',
        'media_type',
        'last_revision_date',
        'next_revision_date',
        'revision_counter',
    ];

    public array $translatable = [
        'definition',
        'translation',
    ];

    protected $casts = [
        'revision_counter' => 'integer',
        'last_revision_date' => 'datetime',
        'next_revision_date' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all revision items for this term.
     */
    public function revisionItems(): MorphMany
    {
        return $this->morphMany(RevisionItem::class, 'revisionable');
    }
}
