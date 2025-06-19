<?php

namespace App\Http\Requests\Admin\CourseCategory;

use Illuminate\Foundation\Http\FormRequest;

class IndexCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view.course_category');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => 'boolean',
            'search' => 'nullable|string',
            'sortBy' => 'nullable|string|in:name,sort_order,created_at',
            'orderBy' => 'nullable|string|in:asc,desc',
            'perPage' => 'nullable|integer|min:1|max:100',
        ];
    }
}
