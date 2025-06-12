<?php

namespace App\Http\Requests\Admin\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.lesson');
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
            'title' => ['sometimes', 'array'],
            'title.*' => ['string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'reshow_incorrect_slides' => ['nullable', 'boolean'],
            'reshow_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'require_correct_answers' => ['nullable', 'boolean'],
        ];

        return $rules;
    }
}
