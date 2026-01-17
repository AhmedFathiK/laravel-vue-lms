<?php

namespace App\Http\Requests\Admin;

use App\Services\Payment\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateBillingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('edit.billing_plans');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'course_ids' => 'array',
            'course_ids.*' => 'exists:courses,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => Currency::validationRules(required: false),
            'billing_interval' => 'nullable|string|in:month,quarter,year,one-time',
            'billing_type' => 'string|in:recurring,one-time,free',
            'access_duration_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'features' => 'nullable|array',
            'features.*' => 'exists:features,id',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->has('currency') && $this->input('currency') !== null) {
            $this->merge(['currency' => Currency::normalize((string) $this->input('currency'))]);
        }

        if ($this->billing_type === 'free') {
            $this->merge([
                'price' => 0,
            ]);
        }
    }
}
