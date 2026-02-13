<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'group' => ['required', 'string'],
            'settings' => ['required', 'array'],
        ];

        $group = $this->input('group', 'general');

        switch ($group) {
            case 'general':
                $rules = array_merge($rules, [
                    'settings.app_name' => ['nullable', 'string', 'max:255'],
                    'settings.app_logo' => ['nullable'], // Can be string (path) or file
                ]);

                // Add specific file validation if a file is uploaded
                if ($this->hasFile('settings.app_logo')) {
                    $rules['settings.app_logo'] = ['image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'];
                }
                break;

            case 'payment':
                // Future implementation example
                $rules = array_merge($rules, [
                    'settings.stripe_key' => ['nullable', 'string'],
                    'settings.stripe_secret' => ['nullable', 'string'],
                ]);
                break;
            
            // Add other groups here as needed
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'settings.app_name' => 'application name',
            'settings.app_logo' => 'application logo',
        ];
    }
}
