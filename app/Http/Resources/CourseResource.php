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
            'is_featured' => $this->is_featured,
            'course_category_id' => $this->course_category_id,
            'main_locale' => $this->main_locale,
            'leaderboard_reset_frequency' => $this->leaderboard_reset_frequency,
            'prerequisites' => $this->prerequisites,
            'final_exam_id' => $this->final_exam_id,
            'placement_exam_id' => $this->placement_exam_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'levels' => $this->whenLoaded('levels'),
            'billing_plans' => $this->whenLoaded('planFeatures', function () {
                return $this->planFeatures->map(fn($pf) => $pf->billingPlan);
            }),
            'levels_count' => $this->levels_count,
            'entitlements_count' => $this->entitlements_count,
        ];
    }
}
