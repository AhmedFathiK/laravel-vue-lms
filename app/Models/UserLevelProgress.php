<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLevelProgress extends Model
{
    protected $table = 'user_level_progress';

    protected $fillable = [
        'user_id',
        'course_id',
        'level_id',
        'status',
        'source_attempt_id',
        'unlocked_at',
        'completed_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_LOCKED = 'locked';
    public const STATUS_UNLOCKED = 'unlocked';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function sourceAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'source_attempt_id');
    }

    /**
     * Scope to find progress for a specific user and level.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
