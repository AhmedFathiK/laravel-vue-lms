<?php

namespace App\Http\Resources\Learner;

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
            'term' => $this->term,
            'meaning' => $this->meaning,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'audio_url' => $this->audio_url,
            'example' => $this->example,
            'example_translation' => $this->example_translation,
            'example_audio_url' => $this->example_audio_url,
        ];
    }
}
