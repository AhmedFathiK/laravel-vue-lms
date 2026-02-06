<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Translatable\HasTranslations;

class Exam extends Model
{
    use HasFactory, HasTranslations;

    // TYPE_PLACEMENT constant kept for legacy reference or logic if needed, 
    // but the column 'type' has been removed from the database.

    protected $fillable = [
        'title',
        'description',
        'instructions',
        'placement_rules',
        'course_id',
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
        'course_id' => 'integer',
        'time_limit' => 'integer',
        'passing_percentage' => 'float',
        'max_attempts' => 'integer',
        'is_active' => 'boolean',
        'randomize_questions' => 'boolean',
        'show_answers' => 'boolean',
        'status' => 'string',
        'placement_rules' => 'array',
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

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function level(): HasOne
    {
        return $this->hasOne(Level::class, 'final_exam_id');
    }

    /**
     * Get the total points for this exam
     */
    public function getTotalPoints(): int
    {
        $totalPoints = 0;

        foreach ($this->sections as $section) {
            foreach ($section->questions as $question) {
                $totalPoints += $question->pivot->points ?? $question->points;
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

    /**
     * Check if this is a placement exam based on rules presence
     */
    public function isPlacement(): bool
    {
        return !empty($this->placement_rules);
    }

    /**
     * Determine placement level ID based on percentage
     */
    public function determinePlacementLevel(float $percentage): ?int
    {
        if (!$this->placement_rules) {
            return null;
        }

        foreach ($this->placement_rules as $rule) {
            // Rules format: [{"min": 0, "max": 40, "level_id": 5}]
            $min = $rule['min'] ?? 0;
            $max = $rule['max'] ?? 100;
            
            // Use strict comparison for lower bound, inclusive for upper bound?
            // Standard: min <= percentage <= max. 
            // Overlap handling: First match wins.
            if ($percentage >= $min && $percentage <= $max) {
                return $rule['level_id'] ?? null;
            }
        }

        return null;
    }
}
