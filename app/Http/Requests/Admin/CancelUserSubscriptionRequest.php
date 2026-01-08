<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelUserSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit.subscriptions') || $this->user()->can('manage.subscriptions');
    }

    /**
     * Get the validation rules that apply to the request.
     *
* @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
