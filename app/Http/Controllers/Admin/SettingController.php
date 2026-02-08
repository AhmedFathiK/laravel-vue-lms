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

        Setting::updateOrCreate(
            ['key' => 'landing_page_config'],
            [
                'value' => json_encode($config),
                'group' => 'landing_page'
            ]
        );

        return response()->json(['message' => 'Landing page settings updated successfully']);
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
                    'buttonText' => 'Get early Access',
                    'buttonLink' => '/#pricing-plan',
                    'secondaryButtonText' => 'Join Community',
                    'secondaryButtonLink' => 'https://discord.gg/12345',
                    'secondaryButtonTarget' => true,
                    'imageLink' => '/',
                    'imageTarget' => true,
                ],
                'visible' => true,
                'wrapper_style' => []
            ],
            [
                'id' => 'features',
                'name' => 'Features',
                'component' => 'Features',
                'props' => (object)[],
                'visible' => true,
                'wrapper_style' => ['background-color' => 'rgb(var(--v-theme-surface))']
            ],
            [
                'id' => 'reviews',
                'name' => 'Customer Reviews',
                'component' => 'CustomersReview',
                'props' => (object)[],
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
                'props' => (object)[],
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
