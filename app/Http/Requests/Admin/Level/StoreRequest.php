<?php

namespace App\Http\Requests\Admin\Level;

use App\Models\Level;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.level');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'is_unlocked' => ['nullable', 'boolean'],
            'is_free' => ['nullable', 'boolean'],
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

        // Automatically calculate the sort_order based on the highest existing order
        if ($this->has('course_id')) {
            $courseId = $this->input('course_id');
            $maxOrder = Level::where('course_id', $courseId)->max('sort_order') ?? 0;
            $this->merge(['sort_order' => $maxOrder + 1]);
        }
    }
}
