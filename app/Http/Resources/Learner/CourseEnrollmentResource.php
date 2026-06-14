<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseEnrollmentResource extends JsonResource
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
            'completion_percentage' => $this->completion_percentage,
            'course' => [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'thumbnail' => $this->course->thumbnail,
            ],
            'user_entitlement' => $this->userEntitlement ? [
                'id' => $this->userEntitlement->id,
                'status' => $this->userEntitlement->status,
                'ends_at' => $this->userEntitlement->ends_at,
                'billing_plan' => [
                    'name' => $this->userEntitlement->billingPlan->name ?? null,
                ],
            ] : null,
        ];
    }
}
