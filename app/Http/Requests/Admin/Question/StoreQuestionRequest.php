<?php

namespace App\Http\Requests\Admin\Question;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.questions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'question_text' => ['required', 'array'],
            'question_text.*' => ['required', 'string'],
            'type' => ['required', Rule::in([
                Question::TYPE_MCQ,
                Question::TYPE_MATCHING,
                Question::TYPE_FILL_BLANK,
                Question::TYPE_REORDERING,
                Question::TYPE_FILL_BLANK_CHOICES,
                Question::TYPE_WRITING,
            ])],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'course_id' => ['nullable', 'exists:courses,id'],
            'level_id' => ['nullable', 'exists:levels,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'explanation' => ['nullable', 'array'],
            'explanation.*' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:0'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', Rule::in(['image', 'audio', 'video'])],
        ];

        // Add validation rules based on question type
        switch ($this->input('type')) {
            case Question::TYPE_MCQ:
                $rules['options'] = ['required', 'array', 'min:2'];
                $rules['options.*.text'] = ['required', 'array'];
                $rules['options.*.text.*'] = ['required', 'string'];
                $rules['options.*.is_correct'] = ['required', 'boolean'];
                break;

            case Question::TYPE_FILL_BLANK:
                $rules['answers'] = ['required', 'array', 'min:1'];
                $rules['answers.*'] = ['required', 'string'];
                $rules['case_sensitive'] = ['boolean'];
                break;

            case Question::TYPE_MATCHING:
                $rules['pairs'] = ['required', 'array', 'min:2'];
                $rules['pairs.*.left'] = ['required', 'array'];
                $rules['pairs.*.left.*'] = ['required', 'string'];
                $rules['pairs.*.right'] = ['required', 'array'];
                $rules['pairs.*.right.*'] = ['required', 'string'];
                break;

            case Question::TYPE_REORDERING:
                $rules['items'] = ['required', 'array', 'min:2'];
                $rules['items.*.text'] = ['required', 'array'];
                $rules['items.*.text.*'] = ['required', 'string'];
                $rules['items.*.position'] = ['required', 'integer', 'min:0'];
                break;

            case Question::TYPE_WRITING:
                $rules['word_limit'] = ['nullable', 'integer', 'min:1'];
                $rules['rubric'] = ['nullable', 'array'];
                $rules['rubric.*'] = ['nullable', 'string'];
                break;
        }

        return $rules;
    }
}
