<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ConceptCategory extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'course_id',
        'title',
    ];

    public array $translatable = [
        'title',
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

    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class, 'category_id');
    }
}
