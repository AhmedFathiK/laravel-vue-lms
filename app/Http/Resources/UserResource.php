<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'capabilities' => $this->capabilities->map(function ($cap) {
                return [
                    'code' => $cap->feature_code,
                    'scope_type' => $cap->scope_type,
                    'scope_id' => $cap->scope_id,
                    'value' => $cap->value,
                ];
            }),
        ];
    }
}