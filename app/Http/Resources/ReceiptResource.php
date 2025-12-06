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
            'receiptNumber' => $this->receipt_number,
            'itemName' => $this->item_name,
            'itemType' => $this->item_type,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'sourceType' => $this->source_type,
            'createdAt' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'subscriptionPlan' => new SubscriptionPlanResource($this->whenLoaded('subscriptionPlan')),
            'isLinkedToSubscription' => $this->when(isset($this->is_linked_to_subscription), $this->is_linked_to_subscription),

            // Voided Info
            'voidedAt' => $this->voided_at,
            'voidReason' => $this->void_reason,
            'voidedBy' => new UserResource($this->whenLoaded('voidedBy')),
        ];
    }
}