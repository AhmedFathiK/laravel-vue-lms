<?php

namespace App\Http\Requests\Admin\Concept;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.terms');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'category_id' => ['nullable', 'integer', 'exists:concept_categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'explanation' => ['sometimes', 'string'],
            'examples' => ['nullable', 'array'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', 'string', 'in:image,video'],
        ];

        return $rules;
    }
}
