<?php

namespace App\Http\Requests\Admin\Lesson;

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
        return $this->user()->can('edit.lessons');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'level_id' => ['sometimes', 'integer', 'exists:levels,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'is_free' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'reshow_incorrect_slides' => ['nullable', 'boolean'],
            'reshow_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'require_correct_answers' => ['nullable', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'delete_thumbnail' => ['nullable', 'boolean'],
        ];

        return $rules;
    }
}
