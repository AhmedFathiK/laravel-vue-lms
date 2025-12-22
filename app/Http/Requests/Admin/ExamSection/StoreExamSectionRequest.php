<?php

namespace App\Http\Requests\Admin\ExamSection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreExamSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.exam_sections');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'exists:exams,id'],
            'title' => ['required', 'array'],
            'title.*' => ['required', 'string'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'instructions' => ['nullable', 'array'],
            'instructions.*' => ['nullable', 'string'],
            'order' => ['required', 'integer', 'min:0'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', Rule::in(['image', 'audio', 'video', 'reading_passage'])],
            'time_limit' => ['nullable', 'integer', 'min:1'],
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['nullable', 'exists:questions,id'],
            'questions.*.order' => ['required_with:questions.*.id', 'integer', 'min:0'],
        ];
    }

}