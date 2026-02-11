<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $settings = $query->get()->pluck('value', 'key');

        return response()->json($settings);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        $group = $data['group'] ?? 'general';

        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                // Check if it's a file upload
                if ($request->hasFile("settings.$key")) {
                    // Delete old file if exists
                    $existingSetting = Setting::where('key', $key)->first();
                    if ($existingSetting && $existingSetting->value) {
                        $oldPath = str_replace('/storage/', '', $existingSetting->value);
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }

                    $file = $request->file("settings.$key");
                    $path = $file->store('settings', 'public');
                    $value = '/storage/' . $path;
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'group' => $group
                    ]
                );
            }
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function getLandingPageConfig()
    {
        $setting = Setting::where('key', 'landing_page_config')->first();

        $storedConfig = $setting ? json_decode($setting->value, true) : [];
        $defaultConfig = $this->getDefaultLandingPageConfig();

        if (empty($storedConfig)) {
            return response()->json($defaultConfig);
        }

        $mergedConfig = [];
        $storedIds = [];

        // Update stored sections with any new default props
        foreach ($storedConfig as $section) {
            $defaultSection = collect($defaultConfig)->firstWhere('id', $section['id']);
            if ($defaultSection) {
                $defaultProps = (array) $defaultSection['props'];
                $storedProps = isset($section['props']) ? (array) $section['props'] : [];

                // Merge props: defaults provide structure, stored values overwrite them if key exists
                $mergedProps = [];
                foreach ($defaultProps as $key => $defaultValue) {
                    $mergedProps[$key] = array_key_exists($key, $storedProps) ? $storedProps[$key] : $defaultValue;
                }
                $section['props'] = $mergedProps;
            }
            $mergedConfig[] = $section;
            $storedIds[] = $section['id'];
        }

        // Add any missing sections from default config
        foreach ($defaultConfig as $section) {
            if (!in_array($section['id'], $storedIds)) {
                $mergedConfig[] = $section;
            }
        }

        return response()->json($mergedConfig);
    }

    public function updateLandingPageConfig(Request $request)
    {
        $config = $request->input('config');

        // Cleanup old images
        $oldSetting = Setting::where('key', 'landing_page_config')->first();
        if ($oldSetting) {
            $oldConfig = json_decode($oldSetting->value, true);
            $oldImages = $this->extractImagePaths($oldConfig);
            $newImages = $this->extractImagePaths($config);

            $imagesToDelete = array_diff($oldImages, $newImages);

            foreach ($imagesToDelete as $imagePath) {
                $path = str_replace('/storage/', '', $imagePath);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        Setting::updateOrCreate(
            ['key' => 'landing_page_config'],
            [
                'value' => json_encode($config),
                'group' => 'landing_page'
            ]
        );

        return response()->json(['message' => 'Landing page settings updated successfully']);
    }

    private function extractImagePaths($config)
    {
        $paths = [];
        if (!is_array($config)) return $paths;

        foreach ($config as $section) {
            if (isset($section['props']) && is_array($section['props'])) {
                if (isset($section['props']['hero_image']) && $section['props']['hero_image'] && str_starts_with($section['props']['hero_image'], '/storage/')) {
                    $paths[] = $section['props']['hero_image'];
                }

                if (isset($section['props']['features']) && is_array($section['props']['features'])) {
                    foreach ($section['props']['features'] as $feature) {
                        if (isset($feature['icon']) && str_starts_with($feature['icon'], '/storage/')) {
                            $paths[] = $feature['icon'];
                        }
                    }
                }

                if (isset($section['props']['reviews']) && is_array($section['props']['reviews'])) {
                    foreach ($section['props']['reviews'] as $review) {
                        if (isset($review['avatar']) && str_starts_with($review['avatar'], '/storage/')) {
                            $paths[] = $review['avatar'];
                        }
                    }
                }
            }
        }
        return $paths;
    }

    public function uploadLandingPageImage(Request $request)
    {
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048'
            ]);

            $file = $request->file('file');
            $path = $file->store('landing-page', 'public');
            return response()->json(['path' => '/storage/' . $path]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    private function getDefaultLandingPageConfig()
    {
        return [
            [
                'id' => 'home',
                'name' => 'Hero Section',
                'component' => 'HeroSection',
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
                    'hero_image' => null, // null means use default hardcoded image
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
                'props' => (object)[],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'pricing',
                'name' => 'Pricing Plans',
                'component' => 'PricingPlans',
                'props' => (object)[],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'stats',
                'name' => 'Product Stats',
                'component' => 'ProductStats',
                'props' => (object)[],
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
                'props' => (object)[],
                'visible' => true,
                'wrapper_style' => []
            ],
            [
                'id' => 'contact-us',
                'name' => 'Contact Us',
                'component' => 'ContactUs',
                'props' => (object)[],
                'visible' => true,
                'wrapper_style' => []
            ],
        ];
    }
}
