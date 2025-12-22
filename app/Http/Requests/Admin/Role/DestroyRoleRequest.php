<?php

namespace App\Http\Requests\Admin\Role;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DestroyRoleRequest extends FormRequest
{

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

            // Check if role is in use
            $userCount = User::role($role->name)->count();
            if ($userCount > 0) {
                $validator->errors()->add('role', "The {$role->name} role cannot be deleted as it is assigned to {$userCount} user(s).");
            }
        });
    }

}