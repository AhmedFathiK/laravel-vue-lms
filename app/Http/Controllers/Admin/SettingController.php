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
}
