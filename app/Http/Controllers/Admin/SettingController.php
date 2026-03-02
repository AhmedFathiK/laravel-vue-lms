<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLandingPageSettingRequest;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function show(string $group): JsonResponse
    {
        if ($group === 'landing-page') {
            return $this->getLandingPageSettings();
        }

        // Default behavior for other groups (general, payment, etc.)
        $settings = Setting::where('group', $group)->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $group): JsonResponse
    {
        if ($group === 'landing-page') {
            // Resolve validation manually for landing page
            $validatedRequest = app(UpdateLandingPageSettingRequest::class);
            return $this->updateLandingPageSettings($validatedRequest);
        }

        // Manually resolve generic validation
        // Merge route group into request for validation if needed
        $request->merge(['group' => $group]);
        $validatedRequest = app(UpdateSettingRequest::class);

        return $this->updateGenericSettings($validatedRequest);
    }

    public function upload(Request $request, string $group): JsonResponse
    {
        if ($group === 'landing-page') {
            return $this->uploadLandingPageImage($request);
        }

        // Generic upload logic if needed
        return response()->json(['message' => 'Upload not implemented for this group'], 501);
    }

    /**
     * Public access for landing page settings (no auth required)
     */
    public function getPublicLandingPageSettings(): JsonResponse
    {
        return $this->getLandingPageSettings();
    }

    private function updateGenericSettings(UpdateSettingRequest $request): JsonResponse
    {
        $data = $request->validated();
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

                // Stringify arrays for storage
                if (is_array($value)) {
                    $value = json_encode($value);
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

    private function getLandingPageSettings()
    {
        $setting = Setting::where('key', 'landing_page_config')->first();

        $storedConfig = $setting ? json_decode($setting->value, true) : [];
        $defaultConfig = UpdateLandingPageSettingRequest::getDefaultConfig();

        if (empty($storedConfig)) {
            return response()->json($defaultConfig);
        }

        $mergedConfig = [];
        $storedIds = [];

        // Update stored sections with any new default props
        foreach ($storedConfig as $section) {
            $defaultSection = collect($defaultConfig)->firstWhere('id', $section['id']);
            if ($defaultSection) {
                // Ensure name is always up to date from code
                $section['name'] = $defaultSection['name'];

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

    private function updateLandingPageSettings(UpdateLandingPageSettingRequest $request)
    {
        $validated = $request->validated();
        $config = $validated['config'] ?? null;
        $sectionId = $validated['section_id'] ?? null;
        $sectionData = $validated['section_data'] ?? null;

        // Fetch existing setting
        $existingSetting = Setting::where('key', 'landing_page_config')->first();
        $storedConfig = $existingSetting ? json_decode($existingSetting->value, true) : [];

        if ($sectionId && $sectionData) {
            // Partial update mode
            $found = false;
            foreach ($storedConfig as &$section) {
                if ($section['id'] === $sectionId) {
                    // Extract old images for this section only to cleanup
                    $oldImages = $this->extractImagePaths([$section]);
                    $newImages = $this->extractImagePaths([$sectionData]);

                    $imagesToDelete = array_diff($oldImages, $newImages);
                    foreach ($imagesToDelete as $imagePath) {
                        $path = str_replace('/storage/', '', $imagePath);
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }

                    $section = $sectionData;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $storedConfig[] = $sectionData;
            }

            $config = $storedConfig;
        } else {
            // Full update mode (legacy)
            if ($existingSetting) {
                $oldConfig = json_decode($existingSetting->value, true);
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
                if (isset($section['props']['faq_image']) && $section['props']['faq_image'] && str_starts_with($section['props']['faq_image'], '/storage/')) {
                    $paths[] = $section['props']['faq_image'];
                }

                if (isset($section['props']['image']) && $section['props']['image'] && str_starts_with($section['props']['image'], '/storage/')) {
                    $paths[] = $section['props']['image'];
                }

                if (isset($section['props']['features']) && is_array($section['props']['features'])) {
                    foreach ($section['props']['features'] as $feature) {
                        if (isset($feature['icon']) && $feature['icon'] && str_starts_with($feature['icon'], '/storage/')) {
                            $paths[] = $feature['icon'];
                        }
                    }
                }

                if (isset($section['props']['team']) && is_array($section['props']['team'])) {
                    foreach ($section['props']['team'] as $member) {
                        if (isset($member['image']) && $member['image'] && str_starts_with($member['image'], '/storage/')) {
                            $paths[] = $member['image'];
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

                if (isset($section['props']['plans']) && is_array($section['props']['plans'])) {
                    foreach ($section['props']['plans'] as $plan) {
                        if (isset($plan['image']) && $plan['image'] && str_starts_with($plan['image'], '/storage/')) {
                            $paths[] = $plan['image'];
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
}
