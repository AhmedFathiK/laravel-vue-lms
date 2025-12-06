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
            'lesson_id' => $this->lesson_id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

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
