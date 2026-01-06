<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
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
            'title' => $this->title,
            'questionText' => $this->question_text,
            'type' => $this->type,
            'content' => $this->content,
            'points' => $this->points,
            'difficulty' => $this->difficulty,
            'tags' => $this->tags,
            'correctFeedback' => $this->correct_feedback,
            'incorrectFeedback' => $this->incorrect_feedback,
            'mediaUrl' => $this->media_url,
            'mediaType' => $this->media_type,
            'audioUrl' => $this->audio_url,
            'terms' => TermResource::collection($this->whenLoaded('terms')),
            'concepts' => $this->whenLoaded('concepts'),
        ];
    }
}
