<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'plan_type' => $this->plan_type,
            'is_active' => $this->is_active,
        ];
    }
}
