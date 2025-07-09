<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
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
            'receipt_number' => $this->receipt_number,
            'item_name' => $this->item_name,
            'item_type' => $this->item_type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'source_type' => $this->source_type,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'subscription_plan' => new SubscriptionPlanResource($this->whenLoaded('subscriptionPlan')),
            'is_linked_to_subscription' => $this->when(isset($this->is_linked_to_subscription), $this->is_linked_to_subscription),
        ];
    }
}