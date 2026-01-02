<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'feedback_sentence' => $this->feedback_sentence,
            'feedback_translation' => $this->feedback_translation,
            'sort_order' => $this->sort_order,
            'question' => new QuestionResource($this->whenLoaded('question')),
            'term' => new TermResource($this->whenLoaded('term')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
