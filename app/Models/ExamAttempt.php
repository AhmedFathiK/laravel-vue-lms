<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory;

    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_GRADED = 'graded';

    protected $fillable = [
        'user_id',
        'exam_id',
        'start_time',
        'end_time',
        'score',
        'max_score',
        'percentage',
        'status',
        'is_passed',
        'attempt_number',
        'time_spent',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'float',
        'max_score' => 'float',
        'percentage' => 'float',
        'is_passed' => 'boolean',
        'attempt_number' => 'integer',
        'time_spent' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class);
    }

    /**
     * Calculate the score for this attempt
     */
    public function calculateScore(): void
    {
        // Skip if there are still writing questions pending review
        if ($this->responses()->where('status', ExamResponse::STATUS_PENDING_REVIEW)->exists()) {
            $this->status = self::STATUS_PENDING_REVIEW;
            $this->save();
            return;
        }

        $totalScore = 0;
        $maxScore = 0;

        foreach ($this->responses as $response) {
            $totalScore += $response->score ?? 0;
            $maxScore += $response->question->points ?? 0;
        }

        $this->score = $totalScore;
        $this->max_score = $maxScore;
        $this->percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        $this->is_passed = $this->percentage >= $this->exam->passing_percentage;
        $this->status = self::STATUS_GRADED;
        $this->save();

        // If this is a level-end exam and the user passed, unlock the next level
        if ($this->exam->type === Exam::TYPE_LEVEL_END && $this->is_passed) {
            $this->unlockNextLevel();
        }

        // If this is a placement test, unlock the appropriate level
        if ($this->exam->type === Exam::TYPE_PLACEMENT && $this->is_passed) {
            $this->unlockPlacementLevel();
        }
    }

    /**
     * Unlock the next level if the user passed a level-end exam
     */
    private function unlockNextLevel(): void
    {
        $currentLevel = $this->exam->level;
        if (!$currentLevel) return;

        $courseId = $currentLevel->course_id;
        $nextLevel = Level::where('course_id', $courseId)
            ->where('sort_order', '>', $currentLevel->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($nextLevel) {
            $nextLevel->is_unlocked = true;
            $nextLevel->save();
        }
    }

    /**
     * Unlock the appropriate level based on placement test score
     */
    private function unlockPlacementLevel(): void
    {
        // Logic to determine which level to unlock based on placement score
        // This is a simple example - you would need to adjust based on your requirements
        $course = $this->exam->course;
        if (!$course) return;

        // Find appropriate level based on score percentage
        // Example: 0-20% = Level 1, 21-40% = Level 2, etc.
        $levelIndex = floor($this->percentage / 20);

        // Make sure we don't exceed the number of levels
        $levels = $course->levels()->orderBy('sort_order')->get();
        $maxIndex = $levels->count() - 1;
        $levelIndex = min($levelIndex, $maxIndex);

        // Unlock the determined level
        if ($levelIndex >= 0 && isset($levels[$levelIndex])) {
            $levels[$levelIndex]->is_unlocked = true;
            $levels[$levelIndex]->save();
        }
    }
}
