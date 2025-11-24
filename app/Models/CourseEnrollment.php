<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CourseEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'enrolled_at',
        'last_accessed_at',
        'is_completed',
        'completion_percentage',
        'completed_at',
        'user_subscription_id',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'is_completed' => 'boolean',
        'completion_percentage' => 'float',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class);
    }

    public function studiedLessons(): HasManyThrough
    {
        return $this->hasManyThrough(UserStudiedLesson::class, Course::class)->where('user_studied_lessons.user_id', $this->user_id);
    }
}
