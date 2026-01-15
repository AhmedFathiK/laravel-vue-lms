<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.subscriptions') || $this->user()->can('manage.subscriptions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'required', 'exists:users,id'],
            'subscription_plan_id' => ['sometimes', 'required', 'exists:subscription_plans,id'],
            'payment_id' => [
                'nullable', 
                'exists:payments,id',
            ],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', 'required', 'string', 'in:active,canceled,expired'],
            'auto_renew' => ['boolean'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $subscription = $this->route('user_subscription');
            
            // Check if payment_id is being modified
            if ($this->has('payment_id')) {
                $newPaymentId = $this->input('payment_id');
                
                if ($subscription->payment_id !== null && $newPaymentId != $subscription->payment_id) {
                    $validator->errors()->add('payment_id', 'Cannot modify payment association for an active subscription.');
                }
            }

            // Check if status is being set to active
            if ($this->has('status') && $this->input('status') === 'active') {
                $paymentId = $this->input('payment_id') ?? $subscription->payment_id;
                
                if ($paymentId) {
                    $payment = \App\Models\Payment::find($paymentId);
                    if ($payment && $payment->status !== 'completed') {
                        $validator->errors()->add('status', 'Cannot activate subscription: Linked payment is not completed.');
                    }
                }
            }
        });
    }
}