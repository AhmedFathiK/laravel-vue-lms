<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

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
        'options',
        'correct_answer',
        'points',
        'difficulty',
        'tags',

        'correct_feedback',
        'incorrect_feedback',
        'media_url',
        'media_type',
        'audio_url',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_answer' => 'array',
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
            ->orderBy('pivot_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class);
    }
}
