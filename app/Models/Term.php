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
        'media_url',
        'media_type',
        'audio_url',
        'example',
        'example_translation',
        'example_audio_url',
    ];

    public array $translatable = [
        'term',
        'definition',
        'example',
        'example_translation',
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
