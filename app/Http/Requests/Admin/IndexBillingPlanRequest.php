<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IndexBillingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view.billing_plans');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => 'nullable|boolean',
            'billing_type' => 'nullable|string|in:recurring,one-time,free',
            'billing_interval' => 'nullable|string|in:day,week,month,year',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer|exists:courses,id',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'integer|exists:features,id',
            'search' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'items_per_page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|array',
            'sort_by.*.key' => 'required|string',
            'sort_by.*.order' => 'required|string|in:asc,desc,ASC,DESC',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Handle camelCase to snake_case if necessary, 
        // though middleware might already be doing this for request keys.
    }
}
