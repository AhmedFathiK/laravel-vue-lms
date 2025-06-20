<?php

namespace App\Http\Requests\Admin\Question;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.questions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'question_text' => ['sometimes', 'required', 'array'],
            'question_text.*' => ['required', 'string'],
            'type' => ['sometimes', 'required', Rule::in([
                Question::TYPE_MCQ,
                Question::TYPE_MATCHING,
                Question::TYPE_FILL_BLANK,
                Question::TYPE_REORDERING,
                Question::TYPE_FILL_BLANK_CHOICES,
                Question::TYPE_WRITING,
            ])],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'array'],
            'points' => ['sometimes', 'required', 'integer', 'min:1'],
            'difficulty' => ['sometimes', 'required', Rule::in(['easy', 'medium', 'hard'])],
            'course_id' => ['nullable', 'exists:courses,id'],
            'level_id' => ['nullable', 'exists:levels,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'explanation' => ['nullable', 'array'],
            'explanation.*' => ['nullable', 'string'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', Rule::in(['image', 'audio', 'video'])],
        ];

        // Add specific rules for different question types
        $type = $this->input('type');

        if ($type) {
            switch ($type) {
                case Question::TYPE_MCQ:
                    $rules['options'] = ['sometimes', 'required', 'array', 'min:2'];
                    $rules['options.*'] = ['required', 'string'];
                    $rules['correct_answer'] = ['sometimes', 'required', 'array', 'min:1'];
                    $rules['correct_answer.*'] = ['required', 'string'];
                    break;

                case Question::TYPE_FILL_BLANK:
                    $rules['correct_answer'] = ['sometimes', 'required', 'array', 'min:1'];
                    $rules['correct_answer.*'] = ['required', 'string'];
                    break;

                case Question::TYPE_FILL_BLANK_CHOICES:
                    $rules['blanks'] = ['sometimes', 'required', 'array', 'min:1'];
                    $rules['blanks.*.placeholder'] = ['nullable', 'string'];
                    $rules['blanks.*.options'] = ['required', 'array', 'min:2'];
                    $rules['blanks.*.options.*'] = ['required', 'string'];
                    $rules['blanks.*.correct_answer'] = ['required', 'string'];
                    break;

                case Question::TYPE_MATCHING:
                    $rules['matching_pairs'] = ['sometimes', 'required', 'array', 'min:2'];
                    $rules['matching_pairs.*.left'] = ['required', 'string'];
                    $rules['matching_pairs.*.right'] = ['required', 'string'];
                    break;

                case Question::TYPE_REORDERING:
                    $rules['reordering_items'] = ['sometimes', 'required', 'array', 'min:2'];
                    $rules['reordering_items.*'] = ['required', 'string'];
                    break;

                case Question::TYPE_WRITING:
                    $rules['grading_guidelines'] = ['nullable', 'string'];
                    $rules['min_words'] = ['nullable', 'integer', 'min:0'];
                    $rules['max_words'] = ['nullable', 'integer', 'min:0'];
                    break;
            }
        }

        return $rules;
    }
}
