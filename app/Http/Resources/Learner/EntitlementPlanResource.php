<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitlementPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
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
        ];
    }
}
