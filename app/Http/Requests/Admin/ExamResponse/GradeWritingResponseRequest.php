<?php

namespace App\Http\Requests\Admin\ExamResponse;

use Illuminate\Foundation\Http\FormRequest;

class GradeWritingResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('grade.exams');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'score' => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ];
    }
}
