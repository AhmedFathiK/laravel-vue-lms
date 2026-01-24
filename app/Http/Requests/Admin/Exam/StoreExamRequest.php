<?php

namespace App\Http\Requests\Admin\Exam;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.exams');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'type' => ['required', 'string'],
            'time_limit' => ['nullable', 'integer', 'min:1'],
            'passing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'randomize_questions' => ['boolean'],
            'show_answers' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],

            // Nested Sections
            'sections' => ['nullable', 'array'],
            'sections.*.title' => ['required', 'string'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.instructions' => ['nullable', 'string'],
            'sections.*.order' => ['required', 'integer'],
            'sections.*.time_limit' => ['nullable', 'integer', 'min:1'],

            // Nested Questions
            'sections.*.questions' => ['nullable', 'array'],
            'sections.*.questions.*.id' => ['nullable', 'integer', 'exists:questions,id'],
            'sections.*.questions.*.type' => ['required', 'string'],
            'sections.*.questions.*.question_text' => ['required', 'string'],
            'sections.*.questions.*.points' => ['required', 'numeric'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.correct_answer' => ['nullable'],
            'sections.*.questions.*.order' => ['required', 'integer'],
            // Simple media for standalone questions
            'sections.*.questions.*.media_url' => ['nullable', 'string'],
            'sections.*.questions.*.media_type' => ['nullable', 'string'],
        ];
    }
}
