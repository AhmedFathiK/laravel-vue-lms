<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.receipts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'course_id' => 'sometimes|exists:courses,id',
            'plan_id' => 'sometimes|exists:subscription_plans,id',
            'amount' => 'sometimes|numeric|min:0',
            'payment_method' => 'sometimes|string|max:255',
            'payment_date' => 'sometimes|date',
            'notes' => 'nullable|string',
            'notify_user' => 'sometimes|boolean',
            'auto_generate_pdf' => 'sometimes|boolean',
            'receipt_number' => 'nullable|string|unique:receipts,receipt_number,' . $this->receipt->id,
            'create_subscription' => 'sometimes|boolean',
        ];
    }
}
