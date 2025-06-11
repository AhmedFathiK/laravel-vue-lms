<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.user');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')->id)
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
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

                // Check if trying to assign super_admin role
                if (in_array('super_admin', $roles) && !$user->hasRole('super_admin')) {
                    $validator->errors()->add('roles', "The super_admin role cannot be assigned through the API.");
                }

                // Prevent removing super_admin role
                if ($user->hasRole('super_admin') && !in_array('super_admin', $roles)) {
                    $validator->errors()->add('roles', "The super_admin role cannot be removed from this user.");
                }
            }
        });
    }
}
