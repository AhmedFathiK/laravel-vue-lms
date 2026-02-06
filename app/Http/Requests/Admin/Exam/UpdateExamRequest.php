<?php

namespace App\Http\Requests\Admin\Exam;

use App\Models\Exam;
use App\Models\Level;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.exams');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'course_id' => ['sometimes', 'exists:courses,id'],
            'time_limit' => ['nullable', 'integer', 'min:1'],
            'passing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'randomize_questions' => ['boolean'],
            'show_answers' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],

            // Placement Rules Validation
            'placement_rules' => ['nullable', 'array', function ($attribute, $value, $fail) {
                if (empty($value)) return;

                $exam = $this->route('exam');
                // Ensure we have the exam model
                if (!($exam instanceof Exam)) {
                    $exam = Exam::find($exam);
                }

                $courseId = $this->course_id ?? ($exam ? $exam->course_id : null);
                
                if (!$courseId) {
                     // Should technically not happen if data integrity is good, or validation fails elsewhere
                     return;
                }

                // 1. Validate Level Ownership
                $levelIds = array_column($value, 'level_id');
                
                if (empty($levelIds)) {
                     $fail("Placement rules must have level_ids.");
                     return;
                }

                $validCount = Level::whereIn('id', $levelIds)
                    ->where('course_id', $courseId)
                    ->count();

                if ($validCount !== count(array_unique($levelIds))) {
                     $fail("All levels in placement rules must belong to the selected course.");
                     return;
                }

                // 2. Validate Coverage (No Gaps, Consistent Boundaries)
                usort($value, fn($a, $b) => ($a['min'] ?? 0) <=> ($b['min'] ?? 0));
                
                $expectedMin = 0;
                
                foreach ($value as $rule) {
                    $min = $rule['min'] ?? 0;
                    $max = $rule['max'] ?? 100;
                    
                    if ($min > $expectedMin) {
                        $fail("Placement rules have a gap between $expectedMin% and $min%.");
                        return;
                    }
                    
                    $expectedMin = max($expectedMin, $max);
                }
                
                if ($expectedMin < 100) {
                    $fail("Placement rules must cover up to 100% (currently covers up to $expectedMin%).");
                }
            }],
            'placement_rules.*.min' => ['required_with:placement_rules', 'numeric', 'min:0'],
            'placement_rules.*.max' => ['required_with:placement_rules', 'numeric', 'min:0', 'max:100', 'gte:placement_rules.*.min'],
            'placement_rules.*.level_id' => ['required_with:placement_rules', 'exists:levels,id'],

            // Nested Sections
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'integer'], // ID for existing sections
            'sections.*.title' => ['required', 'string'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.instructions' => ['nullable', 'string'],
            'sections.*.order' => ['required', 'integer'],
            'sections.*.time_limit' => ['nullable', 'integer', 'min:1'],

            // Nested Questions
            'sections.*.questions' => ['nullable', 'array'],
            'sections.*.questions.*.id' => ['nullable', 'integer', 'exists:questions,id'],
            'sections.*.questions.*.type' => ['required', 'string'],
            'sections.*.questions.*.question_text' => ['required', 'string'],
            'sections.*.questions.*.points' => ['required', 'numeric'],
            'sections.*.questions.*.options' => ['nullable', 'array'],
            'sections.*.questions.*.correct_answer' => ['nullable'],
            'sections.*.questions.*.order' => ['required', 'integer'],
            // Simple media for standalone questions
            'sections.*.questions.*.media_url' => ['nullable', 'string'],
            'sections.*.questions.*.media_type' => ['nullable', 'string'],
        ];
    }
}
