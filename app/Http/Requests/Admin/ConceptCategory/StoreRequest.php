<?php

namespace App\Http\Requests\Admin\ConceptCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create.terms'); // Reusing terms permission
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|array',
            'title.en' => 'required|string|max:255',
        ];
    }
}
