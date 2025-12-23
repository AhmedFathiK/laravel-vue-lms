<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_unlocked' => $this->is_unlocked,
            'is_free' => $this->is_free,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
