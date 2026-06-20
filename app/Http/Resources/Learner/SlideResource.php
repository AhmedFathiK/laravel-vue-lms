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
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'feedback_sentence' => $this->feedback_sentence,
            'feedback_translation' => $this->feedback_translation,
            'question' => new QuestionResource($this->whenLoaded('question')),
            'term' => new TermResource($this->whenLoaded('term')),
        ];
    }
}
