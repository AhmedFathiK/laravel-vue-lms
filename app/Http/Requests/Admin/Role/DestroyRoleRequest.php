<?php

namespace App\Http\Requests\Admin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyRoleRequest extends FormRequest
{
    // Define constants for role IDs
    private const SUPER_ADMIN_ID = 1; // From seeder
    private const STUDENT_ID = 8;     // From seeder (8th role created)

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete.roles');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $role = $this->route('role');

            // Prevent deletion of protected roles
            if ($role->is_protected) {
                $validator->errors()->add('role', "The {$role->name} role cannot be deleted as it is a protected system role.");
                return;
            }

            // Additional check for student role by ID
            if ($role->id === self::STUDENT_ID) {
                $validator->errors()->add('role', "The student role cannot be deleted as it is a protected system role.");
            }
        });
    }
}
