<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\UserEntitlementResource;
use App\Http\Resources\Learner\CourseResource;
use App\Models\UserFeature;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'interface_language' => $this->interface_language,
            'active_course_id' => $this->active_course_id,
            'active_course' => $this->activeCourse ? [
                'id' => $this->activeCourse->id,
                'title' => $this->activeCourse->title,
                'thumbnail' => $this->activeCourse->thumbnail,
            ] : null,
            // Add features to the user resource for CASL/Permissions
            'features' => $this->features->map(function ($feature) {
                return [
                    'code' => $feature->feature_code,
                    'scope_type' => $feature->scope_type,
                    'scope_id' => $feature->scope_id,
                    'value' => $feature->value,
                ];
            }),
        ];
    }
}
