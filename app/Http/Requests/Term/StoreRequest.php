<?php

namespace App\Http\Requests\Term;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.term');
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
            'term' => ['required', 'string', 'max:255'],
            'definition' => ['required', 'array'],
            'definition.en' => ['required', 'string'],
            'translation' => ['nullable', 'array'],
            'translation.en' => ['nullable', 'string'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', 'string', 'in:image,video'],
            'last_revision_date' => ['nullable', 'date'],
            'next_revision_date' => ['nullable', 'date'],
            'revision_counter' => ['nullable', 'integer'],
        ];

        return $rules;
    }
}
