<?php

namespace App\Http\Requests\Admin\Concept;

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
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'explanation' => ['required', 'array'],
            'explanation.en' => ['required', 'string'],
            'type' => ['required', 'string', 'max:50'],
            'examples' => ['nullable', 'array'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', 'string', 'in:image,video'],
        ];

        return $rules;
    }
}
