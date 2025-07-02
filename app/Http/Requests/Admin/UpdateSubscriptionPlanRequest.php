<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('edit.subscriptions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => 'exists:courses,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'billing_cycle' => 'string|in:monthly,quarterly,yearly,one-time',
            'plan_type' => 'string|in:recurring,one-time,free',
            'is_free' => 'boolean',
            'accessible_levels' => 'nullable|array',
            'accessible_levels.*' => 'exists:levels,id',
            'duration_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->has('plan_type') && $this->plan_type === 'free') {
            $this->merge([
                'is_free' => true,
                'price' => 0,
            ]);
        }
    }
}
