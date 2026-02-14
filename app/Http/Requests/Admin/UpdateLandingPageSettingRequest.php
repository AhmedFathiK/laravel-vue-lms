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
            // 'config.*.props' => ['nullable', 'array'], // Removed to ensure strict validation
            'config.*.visible' => ['boolean'],
            'config.*.wrapper_style' => ['nullable', 'array'],

            // Partial update
            'section_id' => ['required_without:config', 'string'],
            'section_data' => ['required_with:section_id', 'array'],
            'section_data.id' => ['required_with:section_data', 'string', 'same:section_id'],
            'section_data.name' => ['nullable', 'string'],
            'section_data.component' => ['nullable', 'string'],
            // 'section_data.props' => ['nullable', 'array'], // Removed to ensure strict validation
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

        // Common rule for URLs and images to prevent XSS (blocks javascript: protocol)
        $safeUrl = ['nullable', 'string', 'not_regex:/^javascript:/i'];

        switch ($sectionId) {
            case 'navbar':
                $rules = [
                    "{$props}.menu_items" => ['required', 'array'],
                    "{$props}.menu_items.*.name" => ['required', 'string'],
                    "{$props}.menu_items.*.to" => ['required', 'string'],
                    "{$props}.menu_items.*.is_hash" => ['boolean'],
                    "{$props}.menu_items.*.target" => ['nullable', 'string', 'in:_self,_blank'],
                ];
                break;

            case 'home':
                $requiredSafeUrl = ['required', 'string', 'not_regex:/^javascript:/i'];
                $rules = [
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.button_text" => ['required', 'string'],
                    "{$props}.button_link" => $requiredSafeUrl,
                    "{$props}.secondary_button_text" => ['required', 'string'],
                    "{$props}.secondary_button_link" => $requiredSafeUrl,
                    "{$props}.secondary_button_target" => ['nullable', 'boolean'],
                    "{$props}.image_link" => $safeUrl,
                    "{$props}.image_target" => ['nullable', 'boolean'],
                    "{$props}.image" => $safeUrl,
                ];
                break;

            case 'features':
                $rules = [
                    "{$props}.tag" => ['required', 'string'],
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.features" => ['nullable', 'array'],
                    "{$props}.features.*.title" => ['required', 'string'],
                    "{$props}.features.*.desc" => ['required', 'string'],
                    "{$props}.features.*.icon" => $safeUrl,
                ];
                break;

            case 'reviews':
                $requiredSafeUrl = ['required', 'string', 'not_regex:/^javascript:/i'];
                $rules = [
                    "{$props}.tag" => ['required', 'string'],
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.reviews" => ['nullable', 'array'],
                    "{$props}.reviews.*.name" => ['required', 'string'],
                    "{$props}.reviews.*.position" => ['required', 'string'],
                    "{$props}.reviews.*.desc" => ['required', 'string'],
                    "{$props}.reviews.*.rating" => ['required', 'numeric', 'min:1', 'max:5'],
                    "{$props}.reviews.*.avatar" => $requiredSafeUrl,
                ];
                break;

            case 'team':
                $rules = [
                    "{$props}.tag" => ['required', 'string'],
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.team" => ['nullable', 'array'],
                    "{$props}.team.*.name" => ['required', 'string'],
                    "{$props}.team.*.position" => ['required', 'string'],
                    "{$props}.team.*.image" => $safeUrl,
                    "{$props}.team.*.background_color" => ['nullable', 'string'], // Backend uses snake_case, frontend sends camelCase, middleware converts
                    "{$props}.team.*.border_color" => ['nullable', 'string'],
                ];
                break;

            case 'pricing':
                $rules = [
                    "{$props}.tag" => ['required', 'string'],
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.save_text" => ['nullable', 'string'],
                    "{$props}.plans" => ['nullable', 'array'],
                    "{$props}.plans.*.title" => ['required', 'string'],
                    "{$props}.plans.*.image" => $safeUrl,
                    "{$props}.plans.*.monthly_price" => ['required', 'numeric'],
                    "{$props}.plans.*.yearly_price" => ['required', 'numeric'],
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
                    "{$props}.stats.*.icon" => $safeUrl,
                    "{$props}.stats.*.color" => ['nullable', 'string'],
                ];
                break;

            case 'faq':
                $rules = [
                    "{$props}.tag" => ['required', 'string'],
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.faq_image" => $safeUrl,
                    "{$props}.faqs" => ['nullable', 'array'],
                    "{$props}.faqs.*.question" => ['required', 'string'],
                    "{$props}.faqs.*.answer" => ['required', 'string'],
                ];
                break;

            case 'banner':
                $rules = [
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.button_text" => ['required', 'string'],
                    "{$props}.image" => $safeUrl,
                ];
                break;

            case 'contact-us':
                $rules = [
                    "{$props}.tag" => ['required', 'string'],
                    "{$props}.title" => ['required', 'string'],
                    "{$props}.subtitle" => ['required', 'string'],
                    "{$props}.form_description" => ['nullable', 'string'],
                    "{$props}.image" => $safeUrl,
                    "{$props}.cards" => ['nullable', 'array'],
                    "{$props}.cards.*.title" => ['required', 'string'],
                    "{$props}.cards.*.icon" => $safeUrl,
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

    public static function getDefaultConfig(): array
    {
        return [
            [
                'id' => 'home',
                'name' => 'Home Cover',
                'component' => 'HomeCover',
                'props' => [
                    'title' => 'One dashboard to manage all your business',
                    'subtitle' => 'Production-ready & easy to use Admin Template for Reliability and Customizability.',
                    'button_text' => 'Get early Access',
                    'button_link' => '/#pricing-plan',
                    'secondary_button_text' => 'Join Community',
                    'secondary_button_link' => 'https://discord.gg/12345',
                    'secondary_button_target' => true,
                    'image_link' => '/',
                    'image_target' => true,
                    'image' => null, // null means use default hardcoded image
                ],
                'visible' => true,
                'wrapper_style' => []
            ],
            [
                'id' => 'features',
                'name' => 'Features',
                'component' => 'Features',
                'props' => [
                    'tag' => 'Useful Features',
                    'title' => 'Everything you need to start your next project',
                    'subtitle' => 'Not just a set of tools, the package includes ready-to-deploy conceptual application.',
                    'features' => [
                        [
                            'title' => 'Quality Code',
                            'desc' => 'Code structure that all developers will easily understand and fall in love with.',
                            'icon' => 'tabler-device-laptop',
                        ],
                        [
                            'title' => 'Continuous Updates',
                            'desc' => 'Free updates for the next 12 months, including new demos and features.',
                            'icon' => 'tabler-rocket',
                        ],
                        [
                            'title' => 'Starter Kit',
                            'desc' => 'Start your project quickly without having to remove unnecessary features.',
                            'icon' => 'tabler-file',
                        ],
                        [
                            'title' => 'API Ready',
                            'desc' => 'Just change the endpoint and see your own data loaded within seconds.',
                            'icon' => 'tabler-check',
                        ],
                        [
                            'title' => 'Excellent Support',
                            'desc' => 'An easy-to-follow doc with lots of references and code examples.',
                            'icon' => 'tabler-user',
                        ],
                        [
                            'title' => 'Well Documented',
                            'desc' => 'An easy-to-follow doc with lots of references and code examples.',
                            'icon' => 'tabler-keyboard',
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'reviews',
                'name' => 'Customer Reviews',
                'component' => 'CustomersReview',
                'props' => [
                    'tag' => 'Real Customers Reviews',
                    'title' => 'What people say',
                    'subtitle' => 'See what our customers have to say about their experience.',
                    'reviews' => [
                        [
                            'desc' => 'I\'ve never used a theme as versatile and flexible as Vuexy. It\'s my go to for building dashboard sites on almost any project.',
                            'rating' => 5,
                            'name' => 'Eugenia Moore',
                            'position' => 'Founder of Hubspot',
                            'avatar' => null,
                        ],
                        [
                            'desc' => 'This template is really clean & well documented. The docs are really easy to understand and it\'s always easy to find a screenshot from their website.',
                            'rating' => 5,
                            'name' => 'Curtis Fletcher',
                            'position' => 'Design Lead at Dribbble',
                            'avatar' => null,
                        ],
                        [
                            'desc' => 'This template is superior in so many ways. The code, the design, the regular updates, the support.. It\'s the whole package. Excellent Work.',
                            'rating' => 4,
                            'name' => 'Eugenia Moore',
                            'position' => 'CTO of Airbnb',
                            'avatar' => null,
                        ],
                        [
                            'desc' => 'All the requirements for developers have been taken into consideration, so I\'m able to build any beautiful interface I want.',
                            'rating' => 5,
                            'name' => 'Sara Smith',
                            'position' => 'Founder of Continental',
                            'avatar' => null,
                        ],
                        [
                            'desc' => 'Vuexy is awesome, and I particularly enjoy knowing that if I get stuck on something, there is always a helpful community to assist me.',
                            'rating' => 5,
                            'name' => 'Tommy haffman',
                            'position' => 'Founder of Levis',
                            'avatar' => null,
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'team',
                'name' => 'Our Team',
                'component' => 'OurTeam',
                'props' => [
                    'tag' => 'Our Great Team',
                    'title' => 'Supported by Real People',
                    'subtitle' => 'Who is behind these great-looking interfaces?',
                    'team' => [
                        [
                            'name' => 'Sophie Gilbert',
                            'position' => 'Project Manager',
                            'image' => null,
                            'backgroundColor' => 'rgba(144, 85, 253, 0.16)',
                            'borderColor' => 'rgba(144, 85, 253,0.16)',
                        ],
                        [
                            'name' => 'Paul Miles',
                            'position' => 'UI Designer',
                            'image' => null,
                            'backgroundColor' => 'rgba(22, 177, 255, 0.16)',
                            'borderColor' => 'rgba(22, 177, 255,0.16)',
                        ],
                        [
                            'name' => 'Nannie Ford',
                            'position' => 'Development Lead',
                            'image' => null,
                            'backgroundColor' => 'rgba(255, 76, 81, 0.16)',
                            'borderColor' => 'rgba(255, 76, 81,0.16)',
                        ],
                        [
                            'name' => 'Chris Watkins',
                            'position' => 'Marketing Manager',
                            'image' => null,
                            'backgroundColor' => 'rgba(86, 202, 0, 0.16)',
                            'borderColor' => 'rgba(86, 202, 0,0.16)',
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'pricing',
                'name' => 'Pricing Plans',
                'component' => 'PricingPlans',
                'props' => [
                    'tag' => 'Pricing Plans',
                    'title' => 'Tailored design plans designed for you',
                    'subtitle' => 'All plans include 40+ advanced tools and features to boost your product. Choose the best plan to fit your needs.',
                    'saveText' => 'Save 25%',
                    'plans' => [
                        [
                            'title' => 'Basic',
                            'image' => null,
                            'monthlyPrice' => 19,
                            'yearlyPrice' => 168,
                            'features' => [
                                'Timeline',
                                'Basic search',
                                'Live chat widget',
                                'Email marketing',
                                'Custom Forms',
                                'Traffic analytics',
                                'Basic Support',
                            ],
                            'supportType' => 'Basic',
                            'supportMedium' => 'Only Email',
                            'respondTime' => 'AVG. Time: 24h',
                            'current' => false,
                        ],
                        [
                            'title' => 'Favourite',
                            'image' => null,
                            'monthlyPrice' => 29,
                            'yearlyPrice' => 264,
                            'features' => [
                                'Everything in basic',
                                'Timeline with database',
                                'Advanced search',
                                'Marketing automation',
                                'Advanced chatbot',
                                'Campaign management',
                                'Collaboration tools',
                            ],
                            'supportType' => 'Standard',
                            'supportMedium' => 'Email & Chat',
                            'respondTime' => 'AVG. Time: 6h',
                            'current' => true,
                        ],
                        [
                            'title' => 'Standard',
                            'image' => null,
                            'monthlyPrice' => 49,
                            'yearlyPrice' => 444,
                            'features' => [
                                'Campaign management',
                                'Timeline with database',
                                'Fuzzy search',
                                'A/B testing sanbox',
                                'Custom permissions',
                                'Social media automation',
                                'Sales automation tools',
                            ],
                            'supportType' => 'Exclusive',
                            'supportMedium' => 'Email, Chat & Google Meet',
                            'respondTime' => 'Live Support',
                            'current' => false,
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'stats',
                'name' => 'Product Stats',
                'component' => 'ProductStats',
                'props' => [
                    'stats' => [
                        [
                            'title' => 'Support Tickets Resolved',
                            'value' => '7.1k+',
                            'icon' => 'tabler-device-laptop',
                            'color' => 'primary',
                        ],
                        [
                            'title' => 'Join creatives community',
                            'value' => '50k+',
                            'icon' => 'tabler-user',
                            'color' => 'success',
                        ],
                        [
                            'title' => 'Highly Rated Products',
                            'value' => '4.8/5',
                            'icon' => 'tabler-diamond',
                            'color' => 'info',
                        ],
                        [
                            'title' => 'Money Back Guarantee',
                            'value' => '100%',
                            'icon' => 'tabler-check',
                            'color' => 'warning',
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => []
            ],
            [
                'id' => 'faq',
                'name' => 'FAQ Section',
                'component' => 'FaqSection',
                'props' => [
                    'tag' => 'FAQ',
                    'title' => 'Frequently Asked questions',
                    'subtitle' => 'Browse through these FAQs to find answers to commonly asked questions.',
                    'faq_image' => null,
                    'faqs' => [
                        [
                            'question' => 'Do you charge for each upgrade?',
                            'answer' => 'Lemon drops chocolate cake gummies carrot cake chupa chups muffin topping. Sesame snaps icing marzipan gummi bears macaroon dragée danish caramels powder. Bear claw dragée pastry topping soufflé. Wafer gummi bears marshmallow pastry pie.',
                        ],
                        [
                            'question' => 'Do I need to purchase a license for each website?',
                            'answer' => 'Dessert ice cream donut oat cake jelly-o pie sugar plum cheesecake. Bear claw dragée oat cake dragée ice cream halvah tootsie roll. Danish cake oat cake pie macaroon tart donut gummies. Jelly beans candy canes carrot cake. Fruitcake chocolate chupa chups.',
                        ],
                        [
                            'question' => 'What is regular license?',
                            'answer' => 'Regular license can be used for end products that do not charge users for access or service(access is free and there will be no monthly entitlement fee). Single regular license can be used for single end product and end product can be used by you or your client. If you want to sell end product to multiple clients then you will need to purchase separate license for each client. The same rule applies if you want to use the same end product on multiple domains(unique setup). For more info on regular license you can check official description.',
                        ],
                        [
                            'question' => 'What is extended license?',
                            'answer' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Nobis et aliquid quaerat possimus maxime! Mollitia reprehenderit neque repellat deleniti delectus architecto dolorum maxime, blanditiis earum ea, incidunt quam possimus cumque.',
                        ],
                        [
                            'question' => 'Which license is applicable for SASS application?',
                            'answer' => 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Sequi molestias exercitationem ab cum nemo facere voluptates veritatis quia, eveniet veniam at et repudiandae mollitia ipsam quasi labore enim architecto non!',
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'banner',
                'name' => 'Banner',
                'component' => 'Banner',
                'props' => [
                    'title' => 'Ready to Get Started?',
                    'subtitle' => 'Start your project with a 14-day free trial',
                    'button_text' => 'Get Started',
                    'image' => null,
                ],
                'visible' => true,
                'wrapper_style' => []
            ],
            [
                'id' => 'contact-us',
                'name' => 'Contact Us',
                'component' => 'ContactUs',
                'props' => [
                    'tag' => 'Contact Us',
                    'title' => 'let\'s work together',
                    'subtitle' => 'Any question or remark? just write us a message',
                    'form_description' => 'If you would like to discuss anything related to payment, account, licensing, partnerships, or have pre-sales questions, you’re at the right place.',
                    'image' => null,
                    'cards' => [
                        [
                            'title' => 'Email',
                            'icon' => 'tabler-mail',
                            'color' => 'primary',
                            'value' => 'example@gmail.com'
                        ],
                        [
                            'title' => 'Phone',
                            'icon' => 'tabler-phone-call',
                            'color' => 'success',
                            'value' => '+1234 568 963'
                        ],
                    ],
                ],
                'visible' => true,
                'wrapper_style' => []
            ],
        ];
    }
}
