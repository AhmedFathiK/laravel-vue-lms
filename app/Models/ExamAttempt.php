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

    protected $appends = [
        'remaining_time',
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
     * Check if the attempt has exceeded the exam time limit
     */
    public function isExpired(): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $exam = $this->exam;
        if (!$exam || !$exam->time_limit) {
            return false;
        }

        $expiryTime = $this->start_time->addMinutes($exam->time_limit);

        return now()->greaterThan($expiryTime);
    }

    /**
     * Get the remaining time in seconds
     */
    public function getRemainingTimeAttribute(): int
    {
        return $this->getRemainingTime();
    }

    /**
     * Get the remaining time in seconds
     */
    public function getRemainingTime(): int
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return 0;
        }

        $exam = $this->exam;
        if (!$exam || !$exam->time_limit) {
            return -1; // No time limit
        }

        $expiryTime = $this->start_time->addMinutes($exam->time_limit);
        $remaining = now()->diffInSeconds($expiryTime, false);

        return (int) max(0, $remaining);
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

        // Pre-load exam structure to get overridden points
        $this->load(['exam.sections.questions']);
        $pointsMap = [];
        $maxScore = 0;
        $validQuestionIds = [];

        if ($this->exam) {
            foreach ($this->exam->sections as $section) {
                foreach ($section->questions as $question) {
                    $points = $question->pivot->points ?? $question->points ?? 0;
                    $pointsMap[$question->id] = $points;
                    $maxScore += $points;
                    $validQuestionIds[] = $question->id;
                }
            }
        }

        $totalScore = 0;

        foreach ($this->responses as $response) {
            // Only count score if the question is still part of the exam
            if (in_array($response->question_id, $validQuestionIds)) {
                $totalScore += $response->score ?? 0;
            }
        }

        $this->score = $totalScore;
        $this->max_score = $maxScore;
        $this->percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
        $this->is_passed = $this->percentage >= $this->exam->passing_percentage;
        $this->status = self::STATUS_GRADED;
        $this->save();

        // Check if this is a level-end exam
        $isLevelEnd = Level::where('final_exam_id', $this->exam_id)->exists();
        if ($isLevelEnd && $this->is_passed) {
            $this->unlockNextLevel();
        }

        // Check if this is a placement test
        $isPlacement = Course::where('placement_exam_id', $this->exam_id)->exists();
        if ($isPlacement && $this->is_passed) {
            $this->unlockPlacementLevel();
        }
    }

    /**
     * Unlock the next level if the user passed a level-end exam
     */
    private function unlockNextLevel(): void
    {
        $level = Level::where('final_exam_id', $this->exam_id)->first();
        if (!$level) return;

        $nextLevel = Level::where('course_id', $level->course_id)
            ->where('sort_order', '>', $level->sort_order)
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
        $course = Course::where('placement_exam_id', $this->exam_id)->first();
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
