<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'content',
        'context_type',
        'video_source',
        'media_url',
        'audio_url',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
