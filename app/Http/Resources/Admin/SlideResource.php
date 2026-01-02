<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'lessonId' => $this->lesson_id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'sortOrder' => $this->sort_order,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'feedbackSentence' => $this->feedback_sentence,
            'feedbackTranslation' => $this->feedback_translation,
            'mediaUrl' => $this->media_url, // Assuming these might exist on model or are dynamically appended
            'mediaType' => $this->media_type,

            // Term (only for term slides)
            'term' => $this->type === 'term' && $this->relationLoaded('term')
                ? new TermResource($this->term)
                : null,

            // Question (only for question slides)
            'question' => in_array($this->type, ['mcq', 'fill_blank', 'fill_blank_choices', 'matching', 'reordering'])
                && $this->relationLoaded('question')
                ? new QuestionResource($this->question)
                : null,
        ];
    }
}
