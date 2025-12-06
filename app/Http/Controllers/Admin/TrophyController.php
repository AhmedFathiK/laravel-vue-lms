<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrophyResource;
use App\Models\Trophy;
use App\Services\TrophyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrophyController extends Controller
{
    protected $trophyService;

    public function __construct(TrophyService $trophyService)
    {
        $this->trophyService = $trophyService;
    }

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

        // Add recipient counts to each trophy
        $trophies->getCollection()->transform(function ($trophy) {
            $stats = $this->trophyService->getTrophyStats($trophy);
            $trophy->recipients_count = $stats['awarded_count'];
            return $trophy;
        });
        return response()->json([
            'items' => TrophyResource::collection($trophies->items()),
            'total_items' => $trophies->total(),
            'current_page' => $trophies->currentPage(),
            'per_page' => $trophies->perPage(),
            'last_page' => $trophies->lastPage(),
        ]);
        return response()->json($trophies);
    }

    /**
     * Store a newly created trophy.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048', // Accept image upload
            'course_id' => 'nullable|exists:courses,id',
            'trigger_type' => [
                'required',
                'string',
                Rule::in([
                    'completed_lesson',
                    'quiz_score',
                    'level_completed',
                    'course_completed',
                    'term_mastered',
                    'streak',
                    'custom'
                ])
            ],
            'trigger_repeat_count' => 'required|integer|min:1',
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

        // Handle icon upload if provided
        $iconUrl = null;
        if ($request->hasFile('icon')) {
            $file = $request->file('icon');
            $path = $file->store('trophies', 'public');
            $iconUrl = Storage::url($path);
        }

        // Prepare data for trophy creation
        $data = [
            'name' => [
                app()->getLocale() => $request->input('name')
            ],
            'description' => $request->has('description') ? [
                app()->getLocale() => $request->input('description')
            ] : null,
            'icon_url' => $iconUrl,
            'course_id' => $request->input('course_id'),
            'trigger_type' => $request->input('trigger_type'),
            'trigger_repeat_count' => $request->input('trigger_repeat_count', 1),
            'points' => $request->input('points', 0),
            'points_threshold' => $request->input('points_threshold'),
            'rarity' => $request->input('rarity', 'common'),
            'is_hidden' => $request->boolean('is_hidden', false),
            'is_active' => $request->boolean('is_active', true),
        ];

        $trophy = Trophy::create($data);

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
        // Add recipient stats
        $stats = $this->trophyService->getTrophyStats($trophy);
        $trophy->recipients_count = $stats['awarded_count'];
        $trophy->recipients_percentage = $stats['percentage'];

        return response()->json($trophy);
    }

    /**
     * Update the specified trophy.
     */
    public function update(Request $request, Trophy $trophy): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048', // Accept image upload
            'course_id' => 'nullable|exists:courses,id',
            'trigger_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'completed_lesson',
                    'quiz_score',
                    'level_completed',
                    'course_completed',
                    'term_mastered',
                    'streak',
                    'custom'
                ])
            ],
            'trigger_repeat_count' => 'sometimes|required|integer|min:1',
            'points' => 'sometimes|required|integer|min:0',
            'points_threshold' => 'nullable|integer|min:0',
            'rarity' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['common', 'uncommon', 'rare', 'epic', 'legendary'])
            ],
            'is_hidden' => 'sometimes|required|boolean',
            'is_active' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle icon upload if provided
        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            if ($trophy->icon_url && Storage::disk('public')->exists(str_replace('/storage/', '', $trophy->icon_url))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $trophy->icon_url));
            }

            $file = $request->file('icon');
            $path = $file->store('trophies', 'public');
            $trophy->icon_url = Storage::url($path);
        }

        // Update translatable fields
        if ($request->has('name')) {
            $trophy->setTranslation('name', app()->getLocale(), $request->input('name'));
        }

        if ($request->has('description')) {
            $trophy->setTranslation('description', app()->getLocale(), $request->input('description'));
        }

        // Update other fields
        if ($request->has('course_id')) {
            $trophy->course_id = $request->input('course_id');
        }

        if ($request->has('trigger_type')) {
            $trophy->trigger_type = $request->input('trigger_type');
        }

        if ($request->has('trigger_repeat_count')) {
            $trophy->trigger_repeat_count = $request->input('trigger_repeat_count');
        }

        if ($request->has('points')) {
            $trophy->points = $request->input('points');
        }

        if ($request->has('points_threshold')) {
            $trophy->points_threshold = $request->input('points_threshold');
        }

        if ($request->has('rarity')) {
            $trophy->rarity = $request->input('rarity');
        }

        if ($request->has('is_hidden')) {
            $trophy->is_hidden = $request->boolean('is_hidden');
        }

        if ($request->has('is_active')) {
            $trophy->is_active = $request->boolean('is_active');
        }

        $trophy->save();

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

        // Delete icon if exists
        if ($trophy->icon_url && Storage::disk('public')->exists(str_replace('/storage/', '', $trophy->icon_url))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $trophy->icon_url));
        }

        $trophy->delete();

        return response()->json([
            'message' => 'Trophy deleted successfully'
        ]);
    }

    /**
     * Get available trigger types.
     */
    public function getTriggerTypes(): JsonResponse
    {
        $triggerTypes = [
            'completed_lesson' => 'Lesson Completed',
            'quiz_score' => 'Quiz Score Achieved',
            'level_completed' => 'Level Completed',
            'course_completed' => 'Course Completed',
            'term_mastered' => 'Term Mastered',
            'streak' => 'Streak Achieved',
            'custom' => 'Custom Achievement'
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
