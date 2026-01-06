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
            'meaning' => ['required', 'string'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_file' => ['nullable', 'file', 'max:10240', 'mimes:jpeg,png,jpg,gif,svg,mp4,webm,ogg,mp3,wav'],
            'media_type' => ['nullable', 'string', 'in:image,image_with_audio,video'],
            'audio_url' => ['nullable', 'string', 'max:255'],
            'audio_file' => ['nullable', 'file', 'max:10240', 'mimes:mp3,wav,ogg'],
            'example' => ['nullable', 'string'],
            'example_translation' => ['nullable', 'string'],
            'example_audio_url' => ['nullable', 'string', 'max:255'],
            'example_audio_file' => ['nullable', 'file', 'max:10240', 'mimes:mp3,wav,ogg'],
        ];

        return $rules;
    }
}
