<?php

namespace App\Http\Resources;

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
            'course_id' => $this->course_id,
            'term' => $this->term,
            'definition' => $this->definition,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'audio_url' => $this->audio_url,
            'example' => $this->example,
            'example_translation' => $this->example_translation,
            'example_audio_url' => $this->example_audio_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
