<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitlementPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_type' => $this->billing_type,
            'billing_interval' => $this->billing_interval,
            'access_type' => $this->access_type,
            'access_duration_days' => $this->access_duration_days,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'course_ids' => $this->whenLoaded('courses', function () {
                return $this->courses->pluck('id');
            }),
            'courses' => $this->whenLoaded('courses', function () {
                return $this->courses->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title, // Translatable handle by model toArray/jsonSerialize
                    ];
                });
            }),
        ];

        if ($this->relationLoaded('planFeatures')) {
            // Extract features
            $features = $this->planFeatures->map(function ($pf) {
                return $pf->feature;
            })->filter(function ($feature) {
                return $feature !== null;
            })->unique('id')->values()->map(function ($feature) {
                return [
                    'id' => $feature->id,
                    'name' => $feature->description ?? $feature->code,
                    'code' => $feature->code,
                ];
            });

            $data['features'] = $features;
        }

        return $data;
    }
}