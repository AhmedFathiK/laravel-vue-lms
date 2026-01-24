<?php

namespace App\Http\Requests\Admin\ExamSection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateExamSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.exam_sections');
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
            'order' => ['required', 'integer', 'min:0'],
            'time_limit' => ['nullable', 'integer', 'min:1'],
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['nullable', 'exists:questions,id'],
            'questions.*.order' => ['required_with:questions.*.id', 'integer', 'min:0'],
            'questions.*.points' => ['nullable', 'integer', 'min:0'],
        ];
    }

}