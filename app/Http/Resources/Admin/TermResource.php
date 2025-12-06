<?php

namespace App\Http\Resources\Admin;

use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermResource extends JsonResource
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
            'courseId' => $this->course_id,
            'term' => $this->term,
            'definition' => $this->definition,
            'mediaUrl' => $this->media_url,
            'mediaType' => $this->media_type,
            'audioUrl' => $this->audio_url,
            'example' => $this->example,
            'exampleTranslation' => $this->example_translation,
            'exampleAudioUrl' => $this->example_audio_url,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
