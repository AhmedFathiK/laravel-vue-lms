<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandingPageSettingRequest extends FormRequest
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
            // Full config update
            'config' => ['required_without:section_id', 'array'],
            'config.*.id' => ['required_with:config', 'string'],
            'config.*.name' => ['nullable', 'string'],
            'config.*.component' => ['nullable', 'string'],
            'config.*.props' => ['nullable', 'array'],
            'config.*.visible' => ['boolean'],
            'config.*.wrapper_style' => ['nullable', 'array'],

            // Partial update
            'section_id' => ['required_without:config', 'string'],
            'section_data' => ['required_with:section_id', 'array'],
            'section_data.id' => ['required_with:section_data', 'string', 'same:section_id'],
            'section_data.name' => ['nullable', 'string'],
            'section_data.component' => ['nullable', 'string'],
            'section_data.props' => ['nullable', 'array'],
            'section_data.visible' => ['boolean'],
            'section_data.wrapper_style' => ['nullable', 'array'],
        ];

        // Add conditional rules based on the section being updated
        // We handle this by checking if we have section_data (partial) or iterate through config (full)

        // However, standard Laravel validation for conditional array content is best handled 
        // by merging rules based on the input data.

        if ($this->has('section_data')) {
            $rules = array_merge($rules, $this->getSectionRules('section_data', $this->input('section_data.id')));
        }

        if ($this->has('config') && is_array($this->input('config'))) {
            foreach ($this->input('config') as $index => $section) {
                if (isset($section['id'])) {
                    $rules = array_merge($rules, $this->getSectionRules("config.{$index}", $section['id']));
                }
            }
        }

        return $rules;
    }

    /**
     * Get specific rules for a section based on its ID
     */
    protected function getSectionRules(string $prefix, string $sectionId): array
    {
        $rules = [];
        $props = "{$prefix}.props";

        switch ($sectionId) {
            case 'home':
                $rules = [
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.button_text" => ['nullable', 'string'],
                    "{$props}.button_link" => ['nullable', 'string'],
                    "{$props}.secondary_button_text" => ['nullable', 'string'],
                    "{$props}.secondary_button_link" => ['nullable', 'string'],
                    "{$props}.secondary_button_target" => ['nullable', 'boolean'],
                    "{$props}.image_link" => ['nullable', 'string'],
                    "{$props}.image_target" => ['nullable', 'boolean'],
                    "{$props}.image" => ['nullable', 'string'],
                ];
                break;

            case 'features':
                $rules = [
                    "{$props}.tag" => ['nullable', 'string'],
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.features" => ['nullable', 'array'],
                    "{$props}.features.*.title" => ['required', 'string'],
                    "{$props}.features.*.desc" => ['nullable', 'string'],
                    "{$props}.features.*.icon" => ['nullable', 'string'],
                ];
                break;

            case 'reviews':
                $rules = [
                    "{$props}.tag" => ['nullable', 'string'],
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.reviews" => ['nullable', 'array'],
                    "{$props}.reviews.*.name" => ['required', 'string'],
                    "{$props}.reviews.*.position" => ['nullable', 'string'],
                    "{$props}.reviews.*.desc" => ['nullable', 'string'],
                    "{$props}.reviews.*.rating" => ['nullable', 'numeric', 'min:0', 'max:5'],
                    "{$props}.reviews.*.avatar" => ['nullable', 'string'],
                ];
                break;

            case 'team':
                $rules = [
                    "{$props}.tag" => ['nullable', 'string'],
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.team" => ['nullable', 'array'],
                    "{$props}.team.*.name" => ['required', 'string'],
                    "{$props}.team.*.position" => ['nullable', 'string'],
                    "{$props}.team.*.image" => ['nullable', 'string'],
                    "{$props}.team.*.background_color" => ['nullable', 'string'], // Backend uses snake_case, frontend sends camelCase, middleware converts
                    "{$props}.team.*.border_color" => ['nullable', 'string'],
                ];
                break;

            case 'pricing':
                $rules = [
                    "{$props}.tag" => ['nullable', 'string'],
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.save_text" => ['nullable', 'string'],
                    "{$props}.plans" => ['nullable', 'array'],
                    "{$props}.plans.*.title" => ['required', 'string'],
                    "{$props}.plans.*.image" => ['nullable', 'string'],
                    "{$props}.plans.*.monthly_price" => ['nullable', 'numeric'],
                    "{$props}.plans.*.yearly_price" => ['nullable', 'numeric'],
                    "{$props}.plans.*.features" => ['nullable', 'array'],
                    "{$props}.plans.*.features.*" => ['string'],
                    "{$props}.plans.*.support_type" => ['nullable', 'string'],
                    "{$props}.plans.*.support_medium" => ['nullable', 'string'],
                    "{$props}.plans.*.respond_time" => ['nullable', 'string'],
                    "{$props}.plans.*.current" => ['boolean'],
                ];
                break;

            case 'stats':
                $rules = [
                    "{$props}.stats" => ['nullable', 'array'],
                    "{$props}.stats.*.title" => ['required', 'string'],
                    "{$props}.stats.*.value" => ['required', 'string'],
                    "{$props}.stats.*.icon" => ['nullable', 'string'],
                    "{$props}.stats.*.color" => ['nullable', 'string'],
                ];
                break;

            case 'faq':
                $rules = [
                    "{$props}.tag" => ['nullable', 'string'],
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.faq_image" => ['nullable', 'string'],
                    "{$props}.faqs" => ['nullable', 'array'],
                    "{$props}.faqs.*.question" => ['required', 'string'],
                    "{$props}.faqs.*.answer" => ['required', 'string'],
                ];
                break;

            case 'banner':
                $rules = [
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.button_text" => ['nullable', 'string'],
                    "{$props}.image" => ['nullable', 'string'],
                ];
                break;

            case 'contact-us':
                $rules = [
                    "{$props}.tag" => ['nullable', 'string'],
                    "{$props}.title" => ['nullable', 'string'],
                    "{$props}.subtitle" => ['nullable', 'string'],
                    "{$props}.form_description" => ['nullable', 'string'],
                    "{$props}.image" => ['nullable', 'string'],
                    "{$props}.cards" => ['nullable', 'array'],
                    "{$props}.cards.*.title" => ['required', 'string'],
                    "{$props}.cards.*.icon" => ['nullable', 'string'],
                    "{$props}.cards.*.color" => ['nullable', 'string'],
                    "{$props}.cards.*.value" => ['required', 'string'],
                ];
                break;
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'config' => 'configuration',
            'section_id' => 'section ID',
            'section_data' => 'section data',
            'section_data.id' => 'section data ID',
            'section_data.props' => 'section properties',
        ];
    }
}
