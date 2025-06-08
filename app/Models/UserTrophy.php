<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTrophy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trophy_id',
        'course_id',
        'context',
    ];

    protected $casts = [
        'context' => 'json',
    ];

    /**
     * Get the user who earned this trophy
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trophy that was earned
     */
    public function trophy(): BelongsTo
    {
        return $this->belongsTo(Trophy::class);
    }

    /**
     * Get the course this trophy was earned in (if any)
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
