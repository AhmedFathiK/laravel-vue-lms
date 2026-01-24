<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionContextResource extends JsonResource
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
            'content' => $this->content,
            'context_type' => $this->context_type,
            'video_source' => $this->video_source,
            'media_url' => $this->media_url,
            'audio_url' => $this->audio_url,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
