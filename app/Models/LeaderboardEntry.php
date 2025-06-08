<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'leaderboard_id',
        'user_id',
        'points',
        'rank',
        'last_updated',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'points' => 'integer',
        'rank' => 'integer',
        'last_updated' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    /**
     * Get the leaderboard this entry belongs to
     */
    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }

    /**
     * Get the user this entry belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
