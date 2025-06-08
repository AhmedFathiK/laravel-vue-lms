<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trophy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrophyController extends Controller
{
    /**
     * Display a listing of trophies.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Trophy::query();

        // Filter by course if specified
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by trigger type if specified
        if ($request->has('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        // Filter by rarity if specified
        if ($request->has('rarity')) {
            $query->where('rarity', $request->rarity);
        }

        // Filter by active status if specified
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by specified field or default to id
        $orderBy = $request->input('order_by', 'id');
        $orderDir = $request->input('order_dir', 'asc');
        $query->orderBy($orderBy, $orderDir);

        $trophies = $query->paginate($request->input('per_page', 15));

        return response()->json($trophies);
    }

    /**
     * Store a newly created trophy.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'description' => 'nullable|array',
            'icon' => 'nullable|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
            'trigger_type' => [
                'required',
                'string',
                Rule::in([
                    'points',
                    'lesson_completed',
                    'exam_passed',
                    'level_completed',
                    'course_completed',
                    'term_mastered',
                    'concept_mastered',
                    'streak',
                    'custom'
                ])
            ],
            'requirements' => 'nullable|array',
            'points' => 'integer|min:0',
            'points_threshold' => 'nullable|integer|min:0',
            'rarity' => [
                'string',
                Rule::in(['common', 'uncommon', 'rare', 'epic', 'legendary'])
            ],
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $trophy = Trophy::create($request->all());

        return response()->json([
            'message' => 'Trophy created successfully',
            'trophy' => $trophy
        ], 201);
    }

    /**
     * Display the specified trophy.
     */
    public function show(Trophy $trophy): JsonResponse
    {
        return response()->json($trophy);
    }

    /**
     * Update the specified trophy.
     */
    public function update(Request $request, Trophy $trophy): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'array',
            'name.en' => 'string|max:255',
            'description' => 'nullable|array',
            'icon' => 'nullable|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
            'trigger_type' => [
                'string',
                Rule::in([
                    'points',
                    'lesson_completed',
                    'exam_passed',
                    'level_completed',
                    'course_completed',
                    'term_mastered',
                    'concept_mastered',
                    'streak',
                    'custom'
                ])
            ],
            'requirements' => 'nullable|array',
            'points' => 'integer|min:0',
            'points_threshold' => 'nullable|integer|min:0',
            'rarity' => [
                'string',
                Rule::in(['common', 'uncommon', 'rare', 'epic', 'legendary'])
            ],
            'is_hidden' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $trophy->update($request->all());

        return response()->json([
            'message' => 'Trophy updated successfully',
            'trophy' => $trophy
        ]);
    }

    /**
     * Remove the specified trophy.
     */
    public function destroy(Trophy $trophy): JsonResponse
    {
        // Check if the trophy has been awarded to users
        $userCount = $trophy->userTrophies()->count();

        if ($userCount > 0) {
            return response()->json([
                'message' => 'Cannot delete trophy that has been awarded to users',
                'user_count' => $userCount
            ], 422);
        }

        $trophy->delete();

        return response()->json([
            'message' => 'Trophy deleted successfully'
        ]);
    }

    /**
     * Get available trigger types for trophies.
     */
    public function getTriggerTypes(): JsonResponse
    {
        $triggerTypes = [
            'points' => 'Points Threshold',
            'lesson_completed' => 'Lesson Completed',
            'exam_passed' => 'Exam Passed',
            'level_completed' => 'Level Completed',
            'course_completed' => 'Course Completed',
            'term_mastered' => 'Term Mastered',
            'concept_mastered' => 'Concept Mastered',
            'streak' => 'Login Streak',
            'custom' => 'Custom Trigger'
        ];

        return response()->json($triggerTypes);
    }

    /**
     * Get available rarity levels for trophies.
     */
    public function getRarityLevels(): JsonResponse
    {
        $rarityLevels = [
            'common' => 'Common',
            'uncommon' => 'Uncommon',
            'rare' => 'Rare',
            'epic' => 'Epic',
            'legendary' => 'Legendary'
        ];

        return response()->json($rarityLevels);
    }
}
