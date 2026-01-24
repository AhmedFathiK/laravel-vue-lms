<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

        if (!$question) {
            return;
        }

        // For writing questions, set as pending review
        // Both models use the same constants, so we can access them from the instance or class
        // But ExamQuestion constants are defined in ExamQuestion class.
        // Let's assume types are strings and consistent.
        if ($question->type === 'writing') {
            $this->status = self::STATUS_PENDING_REVIEW;
            $this->save();
            return;
        }

        // For other question types, auto-grade
        $correctAnswer = $question->correct_answer;
        $userAnswer = $this->user_answer;
        $isCorrect = false;

        switch ($question->type) {
            case 'mcq':
                // Check if arrays are equal (regardless of order)
                if (is_array($correctAnswer) && is_array($userAnswer)) {
                    sort($correctAnswer);
                    sort($userAnswer);
                    $isCorrect = $correctAnswer == $userAnswer;
                } elseif (!is_array($correctAnswer) && !is_array($userAnswer)) {
                    $isCorrect = $correctAnswer == $userAnswer;
                }
                break;

            case 'matching':
                // For matching, all pairs must match exactly
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            case 'fill_blank':
            case 'fill_blank_choices':
                // All blanks must be filled correctly
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            case 'reordering':
                // Order must match exactly
                $isCorrect = $correctAnswer == $userAnswer;
                break;

            default:
                $isCorrect = false;
        }

        // Determine points (check for override in pivot)
        $points = $question->points;
        if ($this->examAttempt) {
            $pivot = DB::table('exam_section_questions')
                ->join('exam_sections', 'exam_sections.id', '=', 'exam_section_questions.exam_section_id')
                ->where('exam_sections.exam_id', $this->examAttempt->exam_id)
                ->where('exam_section_questions.question_id', $question->id)
                ->select('exam_section_questions.points')
                ->first();

            if ($pivot && !is_null($pivot->points)) {
                $points = $pivot->points;
            }
        }

        $this->is_correct = $isCorrect;
        $this->score = $isCorrect ? $points : 0;
        $this->status = self::STATUS_GRADED;
        $this->save();
    }
}
