<?php

namespace App\Http\Requests\Admin\Slide;

use App\Models\Slide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.slides');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'type' => ['required', 'string', Rule::in([
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
            'content' => ['required', 'array'],
            'content.en' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'array'],
            'feedback' => ['nullable', 'array'],
            'feedback.en' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'question_id' => ['nullable', 'integer', 'exists:questions,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
        ];

        return $rules;
    }
}
