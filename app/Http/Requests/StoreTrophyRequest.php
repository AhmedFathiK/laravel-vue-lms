<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrophyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.trophies');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048',
            'course_id' => 'nullable|exists:courses,id',
            'trigger_type' => [
                'required',
                'string',
                Rule::in([
                    'completed_lesson',
                    'quiz_score',
                    'level_completed',
                    'course_completed',
                    'term_mastered',
                    'streak',
                    'custom'
                ])
            ],
            'trigger_repeat_count' => 'required|integer|min:1',
            'points' => 'integer|min:0',
            'points_threshold' => 'nullable|integer|min:0',
            'rarity' => [
                'string',
                Rule::in(['common', 'uncommon', 'rare', 'epic', 'legendary'])
            ],
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
