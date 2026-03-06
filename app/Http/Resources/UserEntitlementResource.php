<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\BillingPlan;

class UserEntitlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Call isActive first as it may update the status if expired
        $isActive = $this->isActive();
        
        // Check for upgrades
        $hasUpgrades = false;
        if ($this->billingPlan) {
            $courseIds = $this->billingPlan->courses->pluck('id');
            if ($courseIds->isNotEmpty()) {
                $hasUpgrades = BillingPlan::whereHas('courses', function ($q) use ($courseIds) {
                    $q->whereIn('courses.id', $courseIds);
                })
                ->where('is_active', true)
                ->where('price', '>', $this->billingPlan->price)
                ->exists();
            }
        }
        
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'billing_plan_id' => $this->billing_plan_id,
            'payment_id' => $this->payment_id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status, // This will reflect updated status if isActive changed it
            'auto_renew' => $this->auto_renew,
            'is_active' => $isActive,
            'has_upgrades' => $hasUpgrades,
            'is_grace_period' => $isActive && $this->ends_at && $this->ends_at->isPast(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'billing_plan' => new EntitlementPlanResource($this->whenLoaded('billingPlan')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'course_id' => $this->billingPlan?->courses?->first()?->id,
        ];
    }
}
