<?php

namespace App\Http\Resources;

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
        $titleTranslations = $this->getTranslatedContent('title');
        $descriptionTranslations = $this->getTranslatedContent('description');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'title_translated' => [
                'main' => $titleTranslations['main'],
                'user' => $titleTranslations['user'],
            ],
            'description' => $this->description,
            'description_translated' => [
                'main' => $descriptionTranslations['main'],
                'user' => $descriptionTranslations['user'],
            ],
            'status' => $this->status,
            'thumbnail' => $this->thumbnail,
            'is_featured' => $this->is_featured,
            'course_category_id' => $this->course_category_id,
            'main_locale' => $this->main_locale,
            'is_free' => $this->is_free,
            'leaderboard_reset_frequency' => $this->leaderboard_reset_frequency,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'levels' => $this->whenLoaded('levels'),
            'subscriptionPlans' => $this->whenLoaded('subscriptionPlans'),
        ];
    }
}
