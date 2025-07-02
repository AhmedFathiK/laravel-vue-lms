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
            'image' => [
                'nullable',
                'mimetypes:image/jpeg,image/png,image/webp',  // Most secure MIME checking
                'max:2048',
                'dimensions:max_width=2048,max_height=2048',
                function ($attribute, $value, $fail) {
                    if (!$value) return;

                    // Add getimagesize() validation that 'image' rule provides
                    $imageInfo = getimagesize($value->getPathname());
                    if (!$imageInfo) {
                        $fail('Invalid image file structure.');
                        return;
                    }

                    // Verify MIME consistency
                    if ($imageInfo['mime'] !== $value->getMimeType()) {
                        $fail('Image file inconsistency detected.');
                    }
                }
            ],
            'video_url' => 'nullable|url',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'price' => 'nullable|numeric|min:0',
            'subscription_only' => 'boolean',
        ];

        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
