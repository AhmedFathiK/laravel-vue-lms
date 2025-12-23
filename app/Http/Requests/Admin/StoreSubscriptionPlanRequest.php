<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create.subscriptions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|string|in:monthly,quarterly,yearly,one-time',
            'plan_type' => 'required|string|in:recurring,one-time,free',
            'is_free' => 'boolean',
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
        if ($this->plan_type === 'free') {
            $this->merge([
                'is_free' => true,
                'price' => 0,
            ]);
        }
    }
}
