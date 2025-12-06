<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\SlideResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
            'level_id' => $this->level_id,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'is_free' => $this->is_free,
            'video_url' => $this->video_url,
            'reshow_incorrect_slides' => $this->reshow_incorrect_slides,
            'reshow_count' => $this->reshow_count,
            'require_correct_answers' => $this->require_correct_answers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'slides' => SlideResource::collection($this->whenLoaded('slides')),
        ];
    }
}
