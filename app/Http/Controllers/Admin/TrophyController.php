<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrophyRequest;
use App\Http\Requests\UpdateTrophyRequest;
use App\Http\Resources\TrophyResource;
use App\Models\Trophy;
use App\Services\TrophyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrophyController extends Controller
{
    protected $trophyService;

    public function __construct(TrophyService $trophyService)
    {
        $this->trophyService = $trophyService;

        $this->middleware('permission:view.trophies')->only('index');
        $this->middleware('permission:create.trophies')->only('store');
        $this->middleware('permission:edit.trophies')->only('update');
        $this->middleware('permission:delete.trophies')->only('destroy');
    }

    /**
     * Display a listing of trophies.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Trophy::query();

        // Apply filters
        $filters = [
            'course_id',
            'trigger_type',
            'rarity',
            'is_active' => fn($query, $value) => $query->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
        ];

        foreach ($filters as $key => $filter) {
            if ($request->has(is_int($key) ? $filter : $key)) {
                $param = is_int($key) ? $filter : $key;
                $value = $request->input($param);
                if (is_callable($filter)) {
                    $filter($query, $value);
                } else {
                    $query->where($param, $value);
                }
            }
        }

        // Order by specified field or default to id
        $sortBy = $request->input('sort_by', 'id');
        $orderBy = $request->input('order_by', 'asc');
        $query->orderBy($sortBy, $orderBy);

        $trophies = $query->paginate($request->input('per_page', 15));

        // Add recipient counts to each trophy
        $trophies->getCollection()->transform(function ($trophy) {
            $stats = $this->trophyService->getTrophyStats($trophy);
            $trophy->recipients_count = $stats['awarded_count'];
            return $trophy;
        });

        return TrophyResource::collection($trophies)->response();
    }

    /**
     * Store a newly created trophy.
     */
    public function store(StoreTrophyRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Handle icon upload if provided
        $iconUrl = null;
        if ($request->hasFile('icon')) {
            $iconUrl = $this->trophyService->handleIconUpload($request->file('icon'));
        }

        // Prepare data for trophy creation
        $data = [
            'name' => [
                app()->getLocale() => $validatedData['name']
            ],
            'description' => isset($validatedData['description']) ? [
                app()->getLocale() => $validatedData['description']
            ] : null,
            'icon_url' => $iconUrl,
            'course_id' => $validatedData['course_id'] ?? null,
            'trigger_type' => $validatedData['trigger_type'],
            'trigger_repeat_count' => $validatedData['trigger_repeat_count'],
            'points' => $validatedData['points'] ?? 0,
            'points_threshold' => $validatedData['points_threshold'] ?? null,
            'rarity' => $validatedData['rarity'] ?? 'common',
            'is_hidden' => $validatedData['is_hidden'] ?? false,
            'is_active' => $validatedData['is_active'] ?? true,
        ];

        $trophy = Trophy::create($data);

        return response()->json([
            'message' => 'Trophy created successfully',
            'trophy' => new TrophyResource($trophy)
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

        return (new TrophyResource($trophy))->response();
    }

    /**
     * Update the specified trophy.
     */
    public function update(UpdateTrophyRequest $request, Trophy $trophy): JsonResponse
    {
        $validatedData = $request->validated();

        // Handle icon upload if provided
        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            $this->trophyService->deleteIcon($trophy);

            $trophy->icon_url = $this->trophyService->handleIconUpload($request->file('icon'));
        }

        // Update translatable fields
        if (isset($validatedData['name'])) {
            $trophy->setTranslation('name', app()->getLocale(), $validatedData['name']);
        }

        if (isset($validatedData['description'])) {
            $trophy->setTranslation('description', app()->getLocale(), $validatedData['description']);
        }

        // Update other fields from validated data
        $trophy->fill(collect($validatedData)->except(['name', 'description', 'icon'])->all());

        $trophy->save();

        return response()->json([
            'message' => 'Trophy updated successfully',
            'trophy' => new TrophyResource($trophy)
        ]);
    }

    /**
     * Remove the specified trophy.
     */
    public function destroy(Trophy $trophy): JsonResponse
    {
        // Check if the trophy has been awarded to users
        if ($trophy->userTrophies()->exists()) {
            return response()->json([
                'message' => 'Cannot delete trophy that has been awarded to users.',
                'user_count' => $trophy->userTrophies()->count(),
            ], 422);
        }

        // Delete icon if exists
        $this->trophyService->deleteIcon($trophy);

        $trophy->delete();

        return response()->json(null, 204);
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
