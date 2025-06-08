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
        return $this->user()->can('create questions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'exists:courses,id'],
            'level_id' => ['nullable', 'exists:levels,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
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
            'options' => ['nullable', 'array'],
            'correct_answer' => ['nullable', 'array'],
            'points' => ['required', 'integer', 'min:1'],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'explanation' => ['nullable', 'array'],
            'explanation.*' => ['nullable', 'string'],
            'media_url' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', Rule::in(['image', 'audio', 'video'])],
        ];
    }
}
