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
            'video_url' => $this->video_url ?? null,
            'is_free' => $this->is_free,
            'is_featured' => $this->is_featured,
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'levels' => LevelResource::collection($this->whenLoaded('levels')),
            'subscription_plans' => SubscriptionPlanResource::collection($this->whenLoaded('subscriptionPlans')),
            'total_students' => $this->enrollments_count ?? 0,
            'total_lectures' => $this->lessons_count ?? 0,
            'is_enrolled' => $request->user('sanctum') ? $this->enrollments()->where('user_id', $request->user('sanctum')->id)->exists() : false,
        ];
    }
}
