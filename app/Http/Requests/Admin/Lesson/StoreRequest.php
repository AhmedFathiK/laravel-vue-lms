<?php

namespace App\Http\Requests\Admin\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.lessons');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'video_url' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $type = $this->input('video_type');
                    if ($type === 'youtube' && !preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/', $value)) {
                        $fail('The video URL must be a valid YouTube URL.');
                    }
                    if ($type === 'vimeo' && !preg_match('/^(https?:\/\/)?(www\.)?(vimeo\.com)\/.+$/', $value)) {
                        $fail('The video URL must be a valid Vimeo URL.');
                    }
                },
            ],
            'video_type' => ['nullable', 'string', 'in:youtube,vimeo,hosted'],
            'reshow_incorrect_slides' => ['nullable', 'boolean'],
            'reshow_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'require_correct_answers' => ['nullable', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];

        return $rules;
    }
}
