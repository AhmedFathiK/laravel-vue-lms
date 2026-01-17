<?php

namespace App\Http\Requests\Admin\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.courses');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'course_category_id' => 'sometimes|required|exists:course_categories,id',
            'main_locale' => 'sometimes|required|string|size:2',
            'level_id' => 'nullable|exists:levels,id',
            'status' => 'sometimes|required|in:draft,published,archived',
            'is_featured' => 'boolean',
            'leaderboard_reset_frequency' => 'required|in:never,daily,weekly,monthly,yearly',
            'image' => 'nullable|image|mimes:jpeg,png,webp|max:2048|dimensions:max_width=2048,max_height=2048',
            'video_url' => 'nullable|url',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'entitlement_only' => 'boolean',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'string|max:255',
        ];

        return $rules;
    }
}
