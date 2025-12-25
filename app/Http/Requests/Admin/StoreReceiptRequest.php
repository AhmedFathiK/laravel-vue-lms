<?php

namespace App\Http\Requests\Admin;

use App\Services\Payment\Currency;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('store.receipts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'amount' => 'required|numeric|min:0',
            'currency' => Currency::validationRules(required: false),
            'payment_method' => 'required|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            'auto_generate_pdf' => 'boolean',
            'receipt_number' => 'nullable|string|unique:receipts,receipt_number',
            'notify_user' => 'boolean',
        ];
    }
}
