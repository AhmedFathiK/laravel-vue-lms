<?php

namespace App\Http\Requests\Admin\Term;

use Illuminate\Foundation\Http\FormRequest;

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
            'term' => ['sometimes', 'string', 'max:255'],
            'definition' => ['sometimes', 'string'],
            'translation' => ['nullable', 'string'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', 'string', 'in:image,image_audio,video'],
            'audio_url' => ['nullable', 'string', 'max:255'],
            'example' => ['nullable', 'string'],
            'example_audio_url' => ['nullable', 'string', 'max:255'],
        ];

        return $rules;
    }
}
