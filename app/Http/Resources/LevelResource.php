<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
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
            'description' => $this->description,
            'sortOrder' => $this->sort_order,
            'status' => $this->status,
            'isUnlocked' => $this->is_unlocked,
            'isFree' => $this->is_free,
            'courseId' => $this->course_id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'lessonsCount' => $this->whenCounted('lessons'),
            'lessons' => $this->whenLoaded('lessons'),
        ];
    }
}
