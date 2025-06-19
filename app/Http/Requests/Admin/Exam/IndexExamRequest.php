<?php

namespace App\Http\Requests\Admin\Exam;

use Illuminate\Foundation\Http\FormRequest;

class IndexExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view.exams');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => 'nullable|integer|exists:courses,id',
            'level_id' => 'nullable|integer|exists:levels,id',
            'lesson_id' => 'nullable|integer|exists:lessons,id',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_by' => 'nullable|string',
            'sort_direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
