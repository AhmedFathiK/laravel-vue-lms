<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Slide extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    const TYPE_MCQ = 'mcq';
    const TYPE_MATCHING = 'matching_pairs';
    const TYPE_REORDERING = 'reordering';
    const TYPE_FILL_BLANK = 'fill_blank';
    const TYPE_FILL_BLANK_CHOICES = 'fill_blank_choices';
    const TYPE_TERM = 'term';
    const TYPE_EXPLANATION = 'explanation';
    const TYPE_QUESTION = 'question';
    const TYPE_TERM_REFERENCE = 'term';

    /**
     * Flag to indicate cascading deletion from parent model
     */
    public static $cascadingDelete = false;

    protected $fillable = [
        'lesson_id',
        'type',
        'content',
        'options',
        'correct_answer',
        'feedback',
        'sort_order',
        'question_id',
        'term_id',
    ];

    public array $translatable = [
        'content',
        'feedback',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'options' => 'array',
        'correct_answer' => 'array',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
