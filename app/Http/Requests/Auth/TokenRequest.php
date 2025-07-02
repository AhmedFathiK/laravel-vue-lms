<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class TokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:5',
            'device_name' => 'required|string|max:255|min:3',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string|in:create,read,update,delete,manage',
            'expires_at' => 'sometimes|date|after:now',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.exists' => 'No account found with this email address',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 6 characters',
            'device_name.required' => 'Device name is required',
            'device_name.min' => 'Device name must be at least 3 characters',
            'device_name.max' => 'Device name cannot exceed 255 characters',
            'abilities.array' => 'Abilities must be an array',
            'abilities.*.in' => 'Invalid ability specified. Allowed abilities: create, read, update, delete, manage',
            'expires_at.date' => 'Expiration date must be a valid date',
            'expires_at.after' => 'Expiration date must be in the future',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'device_name' => 'device name',
            'expires_at' => 'expiration date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'device_name' => trim($this->device_name),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom validation logic can be added here
            if ($this->has('abilities') && empty($this->abilities)) {
                $validator->errors()->add('abilities', 'At least one ability must be specified if abilities are provided.');
            }
        });
    }

    /**
     * Get the token abilities from the request.
     */
    public function getAbilities(): array
    {
        return $this->input('abilities', ['*']); // Default to all abilities
    }

    /**
     * Get the token expiration date.
     */
    public function getExpiresAt(): ?\DateTime
    {
        return $this->has('expires_at') ? new \DateTime($this->expires_at) : null;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
