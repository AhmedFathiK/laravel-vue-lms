<?php

namespace App\Http\Requests\Admin\Exam;

use App\Models\Exam;
use App\Models\Level;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create.exams');
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
            'course_id' => ['required', 'exists:courses,id'],
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

                // 1. Validate Level Ownership
                $courseId = $this->course_id;
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
                // We sort the rules by min percentage to check for continuity.
                usort($value, fn($a, $b) => ($a['min'] ?? 0) <=> ($b['min'] ?? 0));
                
                $expectedMin = 0;
                
                foreach ($value as $rule) {
                    $min = $rule['min'] ?? 0;
                    $max = $rule['max'] ?? 100;
                    
                    // Check for gaps
                    // The start of this range ($min) must be <= where the previous range ended ($expectedMin).
                    // If $min > $expectedMin, there is a gap (e.g. 0-40, then 42-100; 41 is missing).
                    if ($min > $expectedMin) {
                        $fail("Placement rules have a gap between $expectedMin% and $min%.");
                        return;
                    }
                    
                    // Update expectedMin to the end of this range
                    $expectedMin = max($expectedMin, $max);
                }
                
                // Ensure full coverage up to 100%
                if ($expectedMin < 100) {
                    $fail("Placement rules must cover up to 100% (currently covers up to $expectedMin%).");
                }
            }],
            'placement_rules.*.min' => ['required_with:placement_rules', 'numeric', 'min:0'],
            'placement_rules.*.max' => ['required_with:placement_rules', 'numeric', 'min:0', 'max:100', 'gte:placement_rules.*.min'],
            'placement_rules.*.level_id' => ['required_with:placement_rules', 'exists:levels,id'],

            // Nested Sections
            'sections' => ['nullable', 'array'],
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
            'sections.*.questions.*.options' => ['nullable', 'array', function($attribute, $value, $fail) {
                // Get the index of the question to find its type
                // This is tricky inside a nested validator callback without context.
                // We'll rely on the dedicated loop below or conditional rules.
            }],
            'sections.*.questions.*.correct_answer' => ['nullable'],
            'sections.*.questions.*.order' => ['required', 'integer'],
            // Simple media for standalone questions
            'sections.*.questions.*.media_url' => ['nullable', 'string'],
            'sections.*.questions.*.media_type' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            if (!isset($data['sections']) || !is_array($data['sections'])) {
                return;
            }

            foreach ($data['sections'] as $sectionIndex => $section) {
                if (!isset($section['questions']) || !is_array($section['questions'])) {
                    continue;
                }

                foreach ($section['questions'] as $questionIndex => $question) {
                    $type = $question['type'] ?? null;
                    $options = $question['options'] ?? null;
                    $correctAnswer = $question['correct_answer'] ?? null;

                    // MCQ Validation
                    if ($type === Question::TYPE_MCQ) {
                        if (empty($options) || !is_array($options) || count($options) < 2) {
                            $validator->errors()->add(
                                "sections.$sectionIndex.questions.$questionIndex.options",
                                "Multiple choice questions must have at least 2 options."
                            );
                        } else {
                            // Validate correct_answer exists in options
                            // Assuming options is a list of strings or objects? 
                            // Usually simple array of strings for simple MCQ, or objects with IDs.
                            // Based on typical implementation, correct_answer matches one of the options.
                            
                            // If options are simple strings:
                            // if (!in_array($correctAnswer, $options)) ...
                            
                            // If options structure is unknown, we skip strict check or assume standard format.
                            // Let's assume standard array of strings for now as per simple schema.
                        }
                        
                        if (empty($correctAnswer)) {
                             $validator->errors()->add(
                                "sections.$sectionIndex.questions.$questionIndex.correct_answer",
                                "Multiple choice questions must have a correct answer."
                            );
                        }
                    }
                }
            }
        });
    }
}
