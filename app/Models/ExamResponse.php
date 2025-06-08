<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResponse extends Model
{
    use HasFactory;

    const STATUS_GRADED = 'graded';
    const STATUS_PENDING_REVIEW = 'pending_review';

    protected $fillable = [
        'exam_attempt_id',
        'question_id',
        'user_answer',
        'is_correct',
        'score',
        'feedback',
        'status',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'user_answer' => 'array',
        'is_correct' => 'boolean',
        'score' => 'float',
        'graded_at' => 'datetime',
    ];

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Auto-grade the response based on question type and correct answer
     */
    public function autoGrade(): void
    {
        $question = $this->question;

        // For writing questions, set as pending review
        if ($question->type === Question::TYPE_WRITING) {
            $this->status = self::STATUS_PENDING_REVIEW;
            $this->save();
            return;
        }

        // For other question types, auto-grade
        $correctAnswer = $question->correct_answer;
        $userAnswer = $this->user_answer;
        $isCorrect = false;

        switch ($question->type) {
            case Question::TYPE_MCQ:
                // Check if arrays are equal (regardless of order)
                sort($correctAnswer);
                sort($userAnswer);
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            case Question::TYPE_MATCHING:
                // For matching, all pairs must match exactly
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            case Question::TYPE_FILL_BLANK:
            case Question::TYPE_FILL_BLANK_CHOICES:
                // All blanks must be filled correctly
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            case Question::TYPE_REORDERING:
                // Order must match exactly
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            default:
                $isCorrect = false;
        }

        $this->is_correct = $isCorrect;
        $this->score = $isCorrect ? $question->points : 0;
        $this->status = self::STATUS_GRADED;
        $this->save();
    }
}
