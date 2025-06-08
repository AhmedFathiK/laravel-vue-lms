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
        'level_id',
        'lesson_id',
        'question_text',
        'type',
        'options',
        'correct_answer',
        'points',
        'difficulty',
        'tags',
        'explanation',
        'media_url',
        'media_type',
    ];

    public array $translatable = [
        'question_text',
        'explanation',
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

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
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
