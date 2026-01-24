<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Question extends Model
{
    use HasFactory, HasTranslations;

    const TYPE_MCQ = 'mcq';
    const TYPE_MATCHING = 'matching';
    const TYPE_FILL_BLANK = 'fill_blank';
    const TYPE_REORDERING = 'reordering';
    const TYPE_FILL_BLANK_CHOICES = 'fill_blank_choices';
    const TYPE_WRITING = 'writing';

    protected $fillable = [
        'course_id',
        'question_context_id',
        'title',
        'question_text',
        'type',
        'content',
        'points',
        'difficulty',
        'tags',

        'correct_feedback',
        'incorrect_feedback',
        'media_url',
        'media_type',
        'audio_url',
        'video_source',
    ];

    public array $translatable = [
        'title',
        'question_text',
        'correct_feedback',
        'incorrect_feedback',
        'content',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function context(): BelongsTo
    {
        return $this->belongsTo(QuestionContext::class, 'question_context_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }



    public function examSections(): BelongsToMany
    {
        return $this->belongsToMany(ExamSection::class, 'exam_section_questions')
            ->withPivot('order')
            ->orderByPivot('order');
    }

    /**
     * Get the terms related to this question.
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'question_term');
    }

    /**
     * Get the concepts related to this question.
     */
    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(Concept::class, 'question_concept');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class);
    }

    /**
     * Get the correct answer from the content field.
     */
    public function getCorrectAnswerAttribute()
    {
        $content = $this->content;

        // If it's still a string (though translatable usually handles it), decode it
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        if (!is_array($content)) {
            return null;
        }

        // Handle explicit correct answer
        if (isset($content['correct_answer'])) {
            return $content['correct_answer'];
        }
        if (isset($content['correctAnswer'])) {
            return $content['correctAnswer'];
        }

        // Implicit correct answers based on type
        if ($this->type === self::TYPE_REORDERING && isset($content['items'])) {
            // For reordering, the correct answer is the items array itself (in order)
            return $content['items'];
        }

        if ($this->type === self::TYPE_MATCHING && isset($content['pairs'])) {
            // For matching, the correct answer is a map where index matches index
            // Assuming pairs are stored in matching order
            $map = [];
            foreach ($content['pairs'] as $index => $pair) {
                $map[$index] = $index;
            }
            return $map;
        }

        if (($this->type === self::TYPE_FILL_BLANK || $this->type === self::TYPE_FILL_BLANK_CHOICES) && isset($content['blanks'])) {
            $map = [];
            foreach ($content['blanks'] as $index => $blank) {
                if (isset($blank['correct_answer'])) {
                    $map[$index] = $blank['correct_answer'];
                }
            }
            return $map;
        }

        return null;
    }

    public function toArray()
    {
        $attributes = parent::toArray();

        // Ensure translatable fields are correctly translated for the API
        if (isset($this->translatable) && is_array($this->translatable)) {
            foreach ($this->translatable as $field) {
                // If the attribute exists in the array, translate it
                if (array_key_exists($field, $attributes)) {
                    $attributes[$field] = $this->getTranslation($field, app()->getLocale());
                }
            }
        }

        return $attributes;
    }
}
