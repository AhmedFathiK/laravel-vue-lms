<?php

namespace App\Http\Requests\Admin\Course;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.course');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required'],
            'description' => ['nullable'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'thumbnail' => ['nullable', 'sometimes', 'file', 'image', 'max:2048'], // 2MB max
            'is_featured' => ['nullable', 'boolean'],
            'course_category_id' => ['nullable', 'exists:course_categories,id'],
            'is_free' => ['nullable', 'boolean'],
            'leaderboard_reset_frequency' => ['nullable', 'string', 'in:never,weekly,monthly'],
        ];

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('status')) {
            $this->merge(['status' => 'draft']);
        }
    }
}
