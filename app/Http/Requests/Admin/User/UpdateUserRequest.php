<?php

namespace App\Http\Requests\Admin\User;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\Validator as ValidationValidator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')->id)
            ],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed', Password::defaults()],
            'roles' => ['array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'verified' => ['nullable', 'boolean'],
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
            if (isset($this->roles)) {
                $user = $this->route('user');
                $roles = $this->roles;

                // Get super admin role name
                $superAdminRoleName = Role::findOrFail(1)->name;

                // Check if trying to assign Super Admin role
                if (in_array($superAdminRoleName, $roles) && !$user->hasRole($superAdminRoleName)) {
                    $validator->errors()->add('roles', "The {$superAdminRoleName} role cannot be assigned through the API.");
                }

                // Prevent removing Super Admin role
                if ($user->hasRole($superAdminRoleName) && !in_array($superAdminRoleName, $roles)) {
                    $validator->errors()->add('roles', "The {$superAdminRoleName} role cannot be removed from this user.");
                }
            }
        });
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

}