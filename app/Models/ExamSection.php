<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class ExamSection extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'exam_id',
        'title',
        'description',
        'instructions',
        'order',
        'time_limit',
    ];

    public array $translatable = [
        'title',
        'description',
        'instructions',
    ];

    protected $casts = [
        'order' => 'integer',
        'time_limit' => 'integer',
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

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_section_questions')
            ->withPivot('order', 'points')
            ->orderByPivot('order');
    }
}
