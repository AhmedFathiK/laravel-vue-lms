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
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.en' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
            'video_url' => ['nullable', 'string', 'max:255'],
            'reshow_incorrect_slides' => ['nullable', 'boolean'],
            'reshow_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'require_correct_answers' => ['nullable', 'boolean'],
        ];

        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
