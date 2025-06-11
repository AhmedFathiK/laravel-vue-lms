<?php

namespace App\Http\Requests\Admin\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    // Define constants for role types based on IDs
    // These will be more stable than names which can change
    private const SUPER_ADMIN_ID = 1; // From seeder
    private const STUDENT_ID = 8;     // From seeder (8th role created)

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.role');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $this->role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
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

            // Special handling for protected roles
            if ($role->is_protected) {
                // Handle super_admin role (by ID, not name)
                if ($role->id === self::SUPER_ADMIN_ID) {
                    // Ensure super_admin role keeps all permissions
                    if (isset($this->permissions)) {
                        $allPermissions = Permission::pluck('name')->toArray();
                        $missingPermissions = array_diff($allPermissions, $this->permissions);

                        if (!empty($missingPermissions)) {
                            $validator->errors()->add('permissions', 'The super admin role must have all permissions.');
                        }
                    }
                }

                // Handle student role (by ID, not name)
                if ($role->id === self::STUDENT_ID) {
                    $requiredPermissions = [
                        'view.course',
                        'view.level',
                        'view.lesson',
                        'view.slide',
                        'view.term',
                        'view.trophy',
                        'download.receipt'
                    ];

                    if (isset($this->permissions)) {
                        $missingRequired = array_diff($requiredPermissions, $this->permissions);

                        if (!empty($missingRequired)) {
                            $validator->errors()->add(
                                'permissions',
                                'The student role must maintain these basic permissions: ' . implode(', ', $missingRequired)
                            );
                        }
                    }
                }
            }
        });
    }
}
