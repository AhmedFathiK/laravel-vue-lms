<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'thumbnail' => $this->thumbnail,
            'is_free' => $this->is_free,
            'is_featured' => $this->is_featured,
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'levels' => $this->whenLoaded('levels', function () {
                return $this->levels;
            }),
        ];
    }
}
