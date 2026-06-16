<?php

namespace App\Http\Resources\Learner;

use App\Http\Resources\UserEntitlementResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The resource is an array merge of course data and extra fields
        $data = $this->resource;

        return [
            'id' => $data['id'],
            'title' => $data['title'],
            'thumbnail' => $data['thumbnail'] ?? null,
            'levels' => LevelContentResource::collection($data['levels'] ?? []),
            'placement_exam' => isset($data['placementExam']) ? new ItemContentResource($data['placementExam']) : null,
            'final_exam' => isset($data['finalExam']) ? new ItemContentResource($data['finalExam']) : null,
            'entitlement' => isset($data['entitlement']) ? $data['entitlement'] : null,
        ];
    }
}
