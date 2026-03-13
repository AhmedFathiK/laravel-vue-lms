<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\UserEntitlementResource;
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
            'full_name' => $this->full_name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'roles' => $this->roles->pluck('name'),
            'entitlements' => UserEntitlementResource::collection($this->whenLoaded('entitlements')),
            // Add capabilities (features) to the user resource
            'capabilities' => $this->features->map(function ($feature) {
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
