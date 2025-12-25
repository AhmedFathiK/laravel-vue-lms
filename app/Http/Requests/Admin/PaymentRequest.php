<?php

namespace App\Http\Requests\Admin;

use App\Services\Payment\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentRequest extends FormRequest
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
            'payment_method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => Currency::validationRules(required: true),
            'status' => ['required', 'string', 'in:pending,completed,failed,refunded'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_provider' => ['nullable', 'string', 'max:50'],
            'payment_details' => ['nullable', 'array'],
        ];

        // Additional fields for receipt generation
        if ($this->isMethod('post') || $this->input('status') === 'completed') {
            $rules['item_type'] = ['nullable', 'string', 'in:course,subscription_plan'];
            $rules['item_id'] = ['nullable', 'integer', 'required_with:item_type'];
            $rules['item_name'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

}
