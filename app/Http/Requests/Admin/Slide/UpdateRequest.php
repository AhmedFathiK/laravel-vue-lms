<?php

namespace App\Http\Requests\Admin\Slide;

use App\Models\Slide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.slides');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'type' => ['sometimes', 'string', Rule::in([
                Slide::TYPE_MCQ,
                Slide::TYPE_MATCHING,
                Slide::TYPE_REORDERING,
                Slide::TYPE_FILL_BLANK,
                Slide::TYPE_FILL_BLANK_CHOICES,
                Slide::TYPE_TERM,
                Slide::TYPE_EXPLANATION,
                Slide::TYPE_QUESTION,
                Slide::TYPE_TERM_REFERENCE,
            ])],
            'title' => ['nullable', 'string', 'max:255'],
            'question_id' => ['nullable', 'integer', 'exists:questions,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'content' => ['required_if:type,explanation', 'string', 'nullable'],
            'sort_order' => ['nullable', 'integer'],
            'feedback_sentence' => ['nullable', 'string', 'max:255'],
            'feedback_translation' => ['nullable', 'string', 'max:255'],
        ];

        return $rules;
    }

}