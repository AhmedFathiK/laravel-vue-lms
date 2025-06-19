<?php

namespace App\Http\Requests\Admin\Exam;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.exams');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.*' => ['required', 'string'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'instructions' => ['nullable', 'array'],
            'instructions.*' => ['nullable', 'string'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'level_id' => ['nullable', 'exists:levels,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'type' => ['required', Rule::in([
                Exam::TYPE_LESSON,
                Exam::TYPE_LEVEL_END,
                Exam::TYPE_COURSE_END,
                Exam::TYPE_PLACEMENT,
            ])],
            'time_limit' => ['nullable', 'integer', 'min:1'],
            'passing_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'randomize_questions' => ['boolean'],
            'show_answers' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ];
    }
}
