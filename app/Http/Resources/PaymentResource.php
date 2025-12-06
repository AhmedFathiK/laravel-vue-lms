<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'paymentMethod' => $this->payment_method,
            'paymentProvider' => $this->payment_provider,
            'transactionId' => $this->transaction_id,
            'paymentDetails' => $this->payment_details,
        ];
    }
}