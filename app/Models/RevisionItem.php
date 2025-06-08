<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RevisionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'revisionable_id',
        'revisionable_type',
        'difficulty',
        'stability',
        'interval',
        'due_date',
        'last_review',
        'review_count',
        'lapse_count',
        'state',
        'retrievability',
        'response_history',
    ];

    protected $casts = [
        'difficulty' => 'float',
        'stability' => 'float',
        'interval' => 'integer',
        'due_date' => 'datetime',
        'last_review' => 'datetime',
        'review_count' => 'integer',
        'lapse_count' => 'integer',
        'retrievability' => 'float',
        'response_history' => 'array',
    ];

    /**
     * Get the user that owns the revision item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the revisionable model (Term or Concept).
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include items due for revision.
     */
    public function scopeDue($query, $userId = null)
    {
        $query->where('due_date', '<=', now());

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Get the mastery progress records for this revision item.
     */
    public function masteryProgress()
    {
        return $this->hasMany(MasteryProgress::class);
    }
}
