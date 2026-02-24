<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\EntitlementPlanResource;

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
            'is_featured' => $this->is_featured,
            'category' => new CourseCategoryResource($this->whenLoaded('category')),
            'levels' => LevelResource::collection($this->whenLoaded('levels')),
            'billing_plans' => EntitlementPlanResource::collection($this->whenLoaded('billingPlans')),
            'total_students' => $this->enrollments_count ?? 0,
            'total_lectures' => $this->lessons_count ?? 0,
            'is_enrolled' => $request->user('sanctum') ? (
                $this->enrollments()->where('user_id', $request->user('sanctum')->id)->exists()
            ) : false,
            'has_active_access' => $request->user('sanctum') ? (
                $request->user('sanctum')->entitlements()
                ->active()
                ->whereHas('billingPlan.courses', function ($q) {
                    $q->where('courses.id', $this->id);
                })->exists()
            ) : false,
            'active_entitlement' => $request->user('sanctum') ? (
                $request->user('sanctum')->entitlements()
                ->active()
                ->whereHas('billingPlan.courses', function ($q) {
                    $q->where('courses.id', $this->id);
                })
                ->orderBy('created_at', 'desc')
                ->first()
            ) : null,
            'is_grace_period' => $request->user('sanctum') ? (
                $request->user('sanctum')->entitlements()
                ->active()
                ->whereHas('billingPlan.courses', function ($q) {
                    $q->where('courses.id', $this->id);
                })
                ->get()
                ->filter(fn($e) => $e->isActive() && $e->ends_at && $e->ends_at->isPast())
                ->isNotEmpty()
            ) : false,
        ];
    }
}
