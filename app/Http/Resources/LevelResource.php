<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\UserLevelProgress;

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
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'course_id' => $this->course_id,
            'final_exam_id' => $this->final_exam_id,
            'is_free' => $this->is_free,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lessons_count' => $this->whenCounted('lessons'),
            'lessons' => $this->whenLoaded('lessons'),
            'user_status' => $this->relationLoaded('currentUserProgress') 
                ? ($this->currentUserProgress?->status ?? UserLevelProgress::STATUS_LOCKED)
                : UserLevelProgress::STATUS_LOCKED,
        ];
    }
}
