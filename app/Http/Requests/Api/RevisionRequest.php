<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'index', 'getDueItems' => [
                'state' => ['nullable', Rule::in(['new', 'learning', 'review', 'relearning'])],
                'due' => 'nullable|boolean',
                'type' => ['nullable', Rule::in(['term', 'concept'])],
                'course_id' => 'nullable|exists:courses,id',
                'limit' => 'nullable|integer|min:1|max:100',
            ],
            'addItem' => [
                'type' => ['required', Rule::in(['term', 'concept'])],
                'id' => 'required|integer|min:1',
            ],
            'recordResponse' => [
                'grade' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
                'mastery_progress' => 'nullable|array',
                'mastery_progress.*.category' => 'required_with:mastery_progress|string|max:50',
                'mastery_progress.*.description' => 'nullable|string|max:255',
                'mastery_progress.*.strength' => 'nullable|integer|min:1|max:10',
            ],
            'getMasteryProgress' => [
                'course_id' => 'nullable|exists:courses,id',
                'category' => 'nullable|string',
                'strength_below' => 'nullable|integer|min:1|max:10',
            ],
            'generatePractice' => [
                'course_id' => 'nullable|exists:courses,id',
                'count' => 'nullable|integer|min:1|max:20',
                'include_mastery_progress' => 'nullable|boolean',
                'type' => ['nullable', Rule::in(['term', 'concept', 'both'])],
            ],
            'getStatistics' => [
                'course_id' => 'nullable|exists:courses,id',
            ],
            default => [],
        };
    }
}
