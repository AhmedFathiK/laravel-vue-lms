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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'thumbnail' => $this->thumbnail,
            'isFeatured' => $this->is_featured,
            'courseCategoryId' => $this->course_category_id,
            'mainLocale' => $this->main_locale,
            'isFree' => $this->is_free,
            'leaderboardResetFrequency' => $this->leaderboard_reset_frequency,
            'prerequisites' => $this->prerequisites,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'levels' => $this->whenLoaded('levels'),
            'subscriptionPlans' => $this->whenLoaded('subscriptionPlans'),
            'levelsCount' => $this->levels_count,
            'subscriptionsCount' => $this->subscriptions_count,
        ];
    }
}
