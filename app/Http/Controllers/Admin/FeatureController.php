<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    /**
     * Display a listing of the features.
     */
    public function index(Request $request): JsonResponse
    {
        $features = Feature::select('id', 'code', 'description')
            ->get()
            ->map(function($feature) {
            return [
                'id' => $feature->id,
                'name' => $feature->description ?? $feature->code,
                'code' => $feature->code,
                'description' => $feature->description,
            ];
        });

        return response()->json($features);
    }
}
