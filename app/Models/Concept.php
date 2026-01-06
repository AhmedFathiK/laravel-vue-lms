<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Translatable\HasTranslations;

class Concept extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'course_id',
        'category_id',
        'parent_id',
        'lesson_id',
        'title',
        'explanation',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ConceptCategory::class, 'category_id');
    }

    /**
     * Get all revision items for this concept.
     */
    public function revisionItems(): MorphMany
    {
        return $this->morphMany(RevisionItem::class, 'revisionable');
    }

    /**
     * Get the questions related to this concept.
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_concept');
    }
}
