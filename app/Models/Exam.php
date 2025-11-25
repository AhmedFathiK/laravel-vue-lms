<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Exam extends Model
{
    use HasFactory, HasTranslations;

    const TYPE_LESSON = 'lesson_quiz';
    const TYPE_LEVEL_END = 'level_end';
    const TYPE_COURSE_END = 'course_end';
    const TYPE_PLACEMENT = 'placement';

    protected $fillable = [
        'title',
        'description',
        'instructions',
        'course_id',
        'level_id',
        'lesson_id',
        'type',
        'time_limit',
        'passing_percentage',
        'max_attempts',
        'is_active',
        'randomize_questions',
        'show_answers',
        'status',
    ];

    public array $translatable = [
        'title',
        'description',
        'instructions',
    ];

    protected $casts = [
        'time_limit' => 'integer',
        'passing_percentage' => 'float',
        'max_attempts' => 'integer',
        'is_active' => 'boolean',
        'randomize_questions' => 'boolean',
        'show_answers' => 'boolean',
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

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Get the total points for this exam
     */
    public function getTotalPoints(): int
    {
        $totalPoints = 0;

        foreach ($this->sections as $section) {
            foreach ($section->questions as $question) {
                $totalPoints += $question->points;
            }
        }

        return $totalPoints;
    }

    /**
     * Get the total number of questions in this exam
     */
    public function getTotalQuestions(): int
    {
        $totalQuestions = 0;

        foreach ($this->sections as $section) {
            $totalQuestions += $section->questions()->count();
        }

        return $totalQuestions;
    }

    /**
     * Check if the exam has any writing questions that require manual grading
     */
    public function hasWritingQuestions(): bool
    {
        foreach ($this->sections as $section) {
            $hasWriting = $section->questions()
                ->where('type', Question::TYPE_WRITING)
                ->exists();

            if ($hasWriting) {
                return true;
            }
        }

        return false;
    }
}
