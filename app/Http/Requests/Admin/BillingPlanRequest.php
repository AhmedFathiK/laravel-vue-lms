<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BillingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('configure.pricing');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_type' => ['required', 'string', 'in:free,one_time,recurring'],
            'billing_interval' => ['required_if:billing_type,recurring', 'nullable', 'string', 'in:day,week,month,year'],
            'access_type' => ['required', 'string', 'in:lifetime,limited,while_active'],
            'access_duration_days' => ['required_if:access_type,limited', 'nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'features' => ['required', 'array', 'min:1'],
            'features.*' => ['integer', 'exists:features,id'],
        ];
    }

}