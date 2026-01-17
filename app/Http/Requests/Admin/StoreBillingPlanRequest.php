<?php

namespace App\Http\Requests\Admin;

use App\Services\Payment\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreBillingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create.billing_plans');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'exists:courses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required_unless:billing_type,free|numeric|min:0',
            'currency' => array_merge(Currency::validationRules(required: false), ['required_unless:billing_type,free']),
            'billing_interval' => 'required_if:billing_type,recurring|string|in:month,quarter,year,one-time',
            'billing_type' => 'required|string|in:recurring,one-time,free',
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
        // If course is in route, merge it into input for validation
        if ($this->route('course')) {
            $course = $this->route('course');
            $id = $course instanceof \App\Models\Course ? $course->id : $course;
            $this->merge(['course_ids' => [$id]]);
        }

        if (!$this->has('currency') || $this->input('currency') === null) {
            $this->merge(['currency' => Currency::default()]);
        } else {
            $this->merge(['currency' => Currency::normalize((string) $this->input('currency'))]);
        }

        if ($this->billing_type === 'free') {
            $this->merge([
                'price' => 0,
            ]);
        }
    }
}
