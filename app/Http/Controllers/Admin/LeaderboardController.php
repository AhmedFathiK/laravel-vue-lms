<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LeaderboardController extends Controller
{
    protected GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Display a listing of leaderboards.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Leaderboard::query();

        // Filter by course if specified
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by reset frequency if specified
        if ($request->has('reset_frequency')) {
            $query->where('reset_frequency', $request->reset_frequency);
        }

        // Filter by active status if specified
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by specified field or default to id
        $orderBy = $request->input('order_by', 'id');
        $orderDir = $request->input('order_dir', 'asc');
        $query->orderBy($orderBy, $orderDir);

        $leaderboards = $query->paginate($request->input('per_page', 15));

        return response()->json($leaderboards);
    }

    /**
     * Store a newly created leaderboard.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'description' => 'nullable|array',
            'course_id' => 'nullable|exists:courses,id',
            'reset_frequency' => [
                'required',
                'string',
                Rule::in(['daily', 'weekly', 'monthly', 'yearly', 'all_time'])
            ],
            'is_active' => 'boolean',
            'keep_history' => 'boolean',
            'max_entries' => 'integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $leaderboard = Leaderboard::create($request->all());

        return response()->json([
            'message' => 'Leaderboard created successfully',
            'leaderboard' => $leaderboard
        ], 201);
    }

    /**
     * Display the specified leaderboard.
     */
    public function show(Leaderboard $leaderboard): JsonResponse
    {
        return response()->json($leaderboard);
    }

    /**
     * Update the specified leaderboard.
     */
    public function update(Request $request, Leaderboard $leaderboard): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'array',
            'name.en' => 'string|max:255',
            'description' => 'nullable|array',
            'course_id' => 'nullable|exists:courses,id',
            'reset_frequency' => [
                'string',
                Rule::in(['daily', 'weekly', 'monthly', 'yearly', 'all_time'])
            ],
            'is_active' => 'boolean',
            'keep_history' => 'boolean',
            'max_entries' => 'integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $leaderboard->update($request->all());

        return response()->json([
            'message' => 'Leaderboard updated successfully',
            'leaderboard' => $leaderboard
        ]);
    }

    /**
     * Remove the specified leaderboard.
     */
    public function destroy(Leaderboard $leaderboard): JsonResponse
    {
        // Delete all entries first
        $leaderboard->entries()->delete();

        // Then delete the leaderboard
        $leaderboard->delete();

        return response()->json([
            'message' => 'Leaderboard deleted successfully'
        ]);
    }

    /**
     * Get available reset frequencies for leaderboards.
     */
    public function getResetFrequencies(): JsonResponse
    {
        $resetFrequencies = [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'all_time' => 'All Time (No Reset)'
        ];

        return response()->json($resetFrequencies);
    }

    /**
     * View leaderboard entries.
     */
    public function viewEntries(Request $request, Leaderboard $leaderboard): JsonResponse
    {
        $limit = $request->input('limit', $leaderboard->max_entries);
        $userId = $request->input('user_id');

        $result = $this->gamificationService->getLeaderboardEntries($leaderboard->id, $limit, $userId);

        return response()->json([
            'leaderboard' => $leaderboard,
            'entries' => $result['entries'],
            'user_entry' => $result['user_entry']
        ]);
    }

    /**
     * Manually recalculate ranks for a leaderboard.
     */
    public function recalculateRanks(Leaderboard $leaderboard): JsonResponse
    {
        $this->gamificationService->recalculateLeaderboardRanks($leaderboard->id);

        return response()->json([
            'message' => 'Leaderboard ranks recalculated successfully'
        ]);
    }

    /**
     * Manually reset a leaderboard.
     */
    public function resetLeaderboard(Leaderboard $leaderboard): JsonResponse
    {
        // Archive current entries if configured
        if ($leaderboard->keep_history) {
            // Archive logic would go here
        }

        // Delete current entries
        LeaderboardEntry::where('leaderboard_id', $leaderboard->id)->delete();

        return response()->json([
            'message' => 'Leaderboard reset successfully'
        ]);
    }
}
