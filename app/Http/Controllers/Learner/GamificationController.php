<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Leaderboard;
use App\Models\Trophy;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GamificationController extends Controller
{
    protected GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Get the user's trophies.
     */
    public function getUserTrophies(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        $trophies = $this->gamificationService->getUserTrophies($userId, $courseId);

        return response()->json([
            'trophies' => $trophies
        ]);
    }

    /**
     * Get the user's points.
     */
    public function getUserPoints(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');
        $period = $request->input('period'); // daily, weekly, monthly, yearly

        $points = $this->gamificationService->getUserPoints($userId, $courseId, $period);

        // Get point breakdown by type if requested
        $breakdown = [];
        if ($request->boolean('with_breakdown')) {
            $query = \App\Models\UserPoint::where('user_id', $userId);

            if ($courseId) {
                $query->where('course_id', $courseId);
            }

            if ($period) {
                $now = now();
                switch ($period) {
                    case 'daily':
                        $query->whereDate('created_at', $now->toDateString());
                        break;
                    case 'weekly':
                        $query->where('created_at', '>=', $now->startOfWeek()->toDateTimeString());
                        break;
                    case 'monthly':
                        $query->where('created_at', '>=', $now->startOfMonth()->toDateTimeString());
                        break;
                    case 'yearly':
                        $query->where('created_at', '>=', $now->startOfYear()->toDateTimeString());
                        break;
                }
            }

            $breakdown = $query->select('type', \DB::raw('SUM(points) as total'))
                ->groupBy('type')
                ->get()
                ->pluck('total', 'type')
                ->toArray();
        }

        return response()->json([
            'total_points' => $points,
            'breakdown' => $breakdown
        ]);
    }

    /**
     * Get the user's leaderboard rankings.
     */
    public function getUserLeaderboardRankings(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        // Get leaderboards for this course (or global)
        $query = Leaderboard::where('is_active', true);

        if ($courseId) {
            $query->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global leaderboards
            });
        } else {
            $query->whereNull('course_id'); // Only global leaderboards
        }

        $leaderboards = $query->get();

        $rankings = [];
        foreach ($leaderboards as $leaderboard) {
            $entry = $leaderboard->entries()
                ->where('user_id', $userId)
                ->first();

            $rankings[] = [
                'leaderboard_id' => $leaderboard->id,
                'leaderboard_name' => $leaderboard->name,
                'reset_frequency' => $leaderboard->reset_frequency,
                'rank' => $entry ? $entry->rank : null,
                'points' => $entry ? $entry->points : 0,
                'total_participants' => $leaderboard->entries()->count(),
            ];
        }

        return response()->json([
            'rankings' => $rankings
        ]);
    }

    /**
     * View a specific leaderboard.
     */
    public function viewLeaderboard(Request $request, Leaderboard $leaderboard): JsonResponse
    {
        $userId = Auth::id();
        $limit = $request->input('limit', $leaderboard->max_entries);

        $result = $this->gamificationService->getLeaderboardEntries($leaderboard->id, $limit, $userId);

        return response()->json([
            'leaderboard' => $leaderboard,
            'entries' => $result['entries'],
            'user_entry' => $result['user_entry']
        ]);
    }

    /**
     * Get available trophies (visible ones that the user doesn't have yet).
     */
    public function getAvailableTrophies(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        // Get trophies the user already has
        $userTrophyIds = \App\Models\UserTrophy::where('user_id', $userId)
            ->pluck('trophy_id')
            ->toArray();

        // Get visible trophies that the user doesn't have yet
        $query = Trophy::where('is_active', true)
            ->where('is_hidden', false)
            ->whereNotIn('id', $userTrophyIds);

        if ($courseId) {
            $query->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global trophies
            });
        } else {
            $query->whereNull('course_id'); // Only global trophies
        }

        $trophies = $query->get();

        return response()->json([
            'trophies' => $trophies
        ]);
    }

    /**
     * Get the user's trophy statistics.
     */
    public function getTrophyStatistics(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        // Build query for user trophies
        $userTrophyQuery = \App\Models\UserTrophy::where('user_id', $userId);

        if ($courseId) {
            $userTrophyQuery->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global trophies
            });
        }

        $userTrophyCount = $userTrophyQuery->count();

        // Build query for all trophies
        $trophyQuery = Trophy::where('is_active', true);

        if ($courseId) {
            $trophyQuery->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global trophies
            });
        } else {
            $trophyQuery->whereNull('course_id'); // Only global trophies
        }

        $totalTrophyCount = $trophyQuery->count();

        // Get breakdown by rarity
        $rarityBreakdown = \App\Models\UserTrophy::join('trophies', 'user_trophies.trophy_id', '=', 'trophies.id')
            ->where('user_trophies.user_id', $userId)
            ->select('trophies.rarity', \DB::raw('COUNT(*) as count'))
            ->groupBy('trophies.rarity')
            ->get()
            ->pluck('count', 'rarity')
            ->toArray();

        // Fill in missing rarities with zero
        $allRarities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
        foreach ($allRarities as $rarity) {
            if (!isset($rarityBreakdown[$rarity])) {
                $rarityBreakdown[$rarity] = 0;
            }
        }

        return response()->json([
            'earned_trophies' => $userTrophyCount,
            'total_trophies' => $totalTrophyCount,
            'completion_percentage' => $totalTrophyCount > 0 ? round(($userTrophyCount / $totalTrophyCount) * 100, 1) : 0,
            'rarity_breakdown' => $rarityBreakdown
        ]);
    }
}
