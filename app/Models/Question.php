<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Question extends Model
{
    use HasFactory, HasTranslations;

    const TYPE_MCQ = 'mcq';
    const TYPE_MATCHING = 'matching';
    const TYPE_FILL_BLANK = 'fill_blank';
    const TYPE_REORDERING = 'reordering';
    const TYPE_FILL_BLANK_CHOICES = 'fill_blank_choices';
    const TYPE_WRITING = 'writing';

    protected $fillable = [
        'course_id',
        'title',
        'question_text',
        'type',
        'content',
        'points',
        'difficulty',
        'tags',

        'correct_feedback',
        'incorrect_feedback',
        'media_url',
        'media_type',
        'audio_url',
    ];

    public array $translatable = [
        'title',
        'question_text',
        'correct_feedback',
        'incorrect_feedback',
        'content',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }



    public function examSections(): BelongsToMany
    {
        return $this->belongsToMany(ExamSection::class, 'exam_section_questions')
            ->withPivot('order')
            ->orderByPivot('order');
    }

    /**
     * Get the terms related to this question.
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'question_term');
    }

    /**
     * Get the concepts related to this question.
     */
    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(Concept::class, 'question_concept');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class);
    }
}
