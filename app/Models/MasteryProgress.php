<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasteryProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'revision_item_id',
        'category',      // e.g., 'pronunciation', 'meaning', 'usage', etc.
        'description',   // specific detail about what the user is working on
        'strength',      // 1-10 scale where 1 is beginner, 10 is mastered
        'last_identified_at',
    ];

    protected $casts = [
        'strength' => 'integer',
        'last_identified_at' => 'datetime',
    ];

    /**
     * Get the user that owns the mastery progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the revision item this mastery progress belongs to.
     */
    public function revisionItem(): BelongsTo
    {
        return $this->belongsTo(RevisionItem::class);
    }
}
