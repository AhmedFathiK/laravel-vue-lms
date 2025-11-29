<?php

namespace App\Http\Requests\Admin\Term;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.terms');
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
            'definition' => ['required', 'string'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', 'string', 'in:image,image_with_audio,video'],
            'audio_url' => ['nullable', 'string', 'max:255'],
            'example' => ['nullable', 'string'],
            'example_translation' => ['nullable', 'string'],
            'example_audio_url' => ['nullable', 'string', 'max:255'],
        ];

        return $rules;
    }
}
