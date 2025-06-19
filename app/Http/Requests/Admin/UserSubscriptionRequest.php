<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage.subscriptions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'user_id' => ['required', 'exists:users,id'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', 'string', 'in:active,canceled,expired'],
            'auto_renew' => ['boolean'],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ];

        return $rules;
    }
}
