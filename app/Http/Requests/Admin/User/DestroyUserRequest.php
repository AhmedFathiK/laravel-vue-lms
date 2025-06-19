<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete.users');
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
            $user = $this->route('user');

            // Protect only Super Admin users
            if ($user->hasRole('Super Admin')) {
                $validator->errors()->add('user', "Users with the Super Admin role cannot be deleted.");
            }
        });
    }
}
