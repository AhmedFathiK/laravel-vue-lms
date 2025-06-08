<?php

namespace App\Services;

use App\Models\Trophy;
use App\Models\UserTrophy;
use App\Models\UserPoint;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    /**
     * Award points to a user
     * 
     * @param int $userId User ID
     * @param string $type Type of action (e.g., 'lesson_completed', 'exam_passed')
     * @param int $points Number of points to award
     * @param int|null $courseId Optional course ID
     * @param string|null $description Optional description
     * @return UserPoint
     */
    public function awardPoints(int $userId, string $type, int $points, ?int $courseId = null, ?string $description = null): UserPoint
    {
        $userPoint = new UserPoint([
            'user_id' => $userId,
            'course_id' => $courseId,
            'type' => $type,
            'points' => $points,
            'description' => $description,
        ]);

        $userPoint->save();

        // Update leaderboards
        $this->updateLeaderboards($userId, $courseId);

        // Check for point-based trophies
        $this->checkPointBasedTrophies($userId, $courseId);

        return $userPoint;
    }

    /**
     * Award a trophy to a user
     * 
     * @param int $userId User ID
     * @param int $trophyId Trophy ID
     * @param int|null $courseId Optional course ID
     * @param string|null $context Optional context data (JSON)
     * @return UserTrophy|null Returns UserTrophy if awarded, null if already owned
     */
    public function awardTrophy(int $userId, int $trophyId, ?int $courseId = null, ?string $context = null): ?UserTrophy
    {
        // Check if user already has this trophy
        $existingTrophy = UserTrophy::where('user_id', $userId)
            ->where('trophy_id', $trophyId)
            ->first();

        if ($existingTrophy) {
            return null; // User already has this trophy
        }

        $trophy = Trophy::find($trophyId);
        if (!$trophy) {
            return null; // Trophy doesn't exist
        }

        $userTrophy = new UserTrophy([
            'user_id' => $userId,
            'trophy_id' => $trophyId,
            'course_id' => $courseId,
            'context' => $context,
        ]);

        $userTrophy->save();

        // Award points for getting a trophy if configured
        if ($trophy->points > 0) {
            $this->awardPoints(
                $userId,
                'trophy_earned',
                $trophy->points,
                $courseId,
                "Earned trophy: {$trophy->name}"
            );
        }

        return $userTrophy;
    }

    /**
     * Check for trophies based on accumulated points
     * 
     * @param int $userId User ID
     * @param int|null $courseId Optional course ID
     */
    protected function checkPointBasedTrophies(int $userId, ?int $courseId = null): void
    {
        // Get total points for this user
        $query = UserPoint::where('user_id', $userId);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $totalPoints = $query->sum('points');

        // Find point-based trophies that the user qualifies for
        $trophyQuery = Trophy::where('trigger_type', 'points')
            ->where('points_threshold', '<=', $totalPoints)
            ->orderBy('points_threshold', 'desc');

        if ($courseId) {
            $trophyQuery->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global trophies
            });
        } else {
            $trophyQuery->whereNull('course_id'); // Only global trophies
        }

        $trophies = $trophyQuery->get();

        // Award each trophy
        foreach ($trophies as $trophy) {
            $this->awardTrophy(
                $userId,
                $trophy->id,
                $courseId,
                json_encode(['points' => $totalPoints])
            );
        }
    }

    /**
     * Update leaderboards with user's current points
     * 
     * @param int $userId User ID
     * @param int|null $courseId Optional course ID
     */
    protected function updateLeaderboards(int $userId, ?int $courseId = null): void
    {
        // Find active leaderboards
        $query = Leaderboard::where('is_active', true);

        if ($courseId) {
            $query->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global leaderboards
            });
        }

        $leaderboards = $query->get();

        foreach ($leaderboards as $leaderboard) {
            $this->updateLeaderboardEntry($leaderboard, $userId);
        }
    }

    /**
     * Update a specific leaderboard entry for a user
     * 
     * @param Leaderboard $leaderboard Leaderboard model
     * @param int $userId User ID
     */
    protected function updateLeaderboardEntry(Leaderboard $leaderboard, int $userId): void
    {
        $now = Carbon::now();

        // Calculate points based on leaderboard settings
        $pointsQuery = UserPoint::where('user_id', $userId);

        // Apply course filter if leaderboard is course-specific
        if ($leaderboard->course_id) {
            $pointsQuery->where('course_id', $leaderboard->course_id);
        }

        // Apply time range filter based on reset frequency
        if ($leaderboard->reset_frequency !== 'all_time') {
            switch ($leaderboard->reset_frequency) {
                case 'daily':
                    $pointsQuery->whereDate('created_at', $now->toDateString());
                    break;
                case 'weekly':
                    $pointsQuery->where('created_at', '>=', $now->startOfWeek()->toDateTimeString());
                    break;
                case 'monthly':
                    $pointsQuery->where('created_at', '>=', $now->startOfMonth()->toDateTimeString());
                    break;
                case 'yearly':
                    $pointsQuery->where('created_at', '>=', $now->startOfYear()->toDateTimeString());
                    break;
            }
        }

        $totalPoints = $pointsQuery->sum('points');

        // Update or create leaderboard entry
        LeaderboardEntry::updateOrCreate(
            [
                'leaderboard_id' => $leaderboard->id,
                'user_id' => $userId,
            ],
            [
                'points' => $totalPoints,
                'last_updated' => $now,
            ]
        );

        // Recalculate ranks for this leaderboard
        $this->recalculateLeaderboardRanks($leaderboard->id);
    }

    /**
     * Recalculate ranks for all entries in a leaderboard
     * 
     * @param int $leaderboardId Leaderboard ID
     */
    public function recalculateLeaderboardRanks(int $leaderboardId): void
    {
        // Use a window function to calculate ranks
        DB::statement("
            UPDATE leaderboard_entries
            SET rank = ranks.rank
            FROM (
                SELECT 
                    id, 
                    RANK() OVER (ORDER BY points DESC) as rank
                FROM leaderboard_entries
                WHERE leaderboard_id = ?
            ) ranks
            WHERE leaderboard_entries.id = ranks.id
            AND leaderboard_entries.leaderboard_id = ?
        ", [$leaderboardId, $leaderboardId]);
    }

    /**
     * Reset leaderboards based on their reset frequency
     */
    public function resetLeaderboards(): void
    {
        $now = Carbon::now();

        // Daily reset
        if ($now->hour === 0 && $now->minute === 0) {
            $this->resetLeaderboardsByFrequency('daily');
        }

        // Weekly reset (assuming Monday is the first day of the week)
        if ($now->dayOfWeek === 1 && $now->hour === 0 && $now->minute === 0) {
            $this->resetLeaderboardsByFrequency('weekly');
        }

        // Monthly reset
        if ($now->day === 1 && $now->hour === 0 && $now->minute === 0) {
            $this->resetLeaderboardsByFrequency('monthly');
        }

        // Yearly reset
        if ($now->month === 1 && $now->day === 1 && $now->hour === 0 && $now->minute === 0) {
            $this->resetLeaderboardsByFrequency('yearly');
        }
    }

    /**
     * Reset leaderboards with a specific frequency
     * 
     * @param string $frequency Leaderboard reset frequency
     */
    protected function resetLeaderboardsByFrequency(string $frequency): void
    {
        // Find leaderboards with the specified reset frequency
        $leaderboards = Leaderboard::where('reset_frequency', $frequency)
            ->where('is_active', true)
            ->get();

        foreach ($leaderboards as $leaderboard) {
            // Archive current entries if configured
            if ($leaderboard->keep_history) {
                $this->archiveLeaderboardEntries($leaderboard->id);
            }

            // Delete current entries
            LeaderboardEntry::where('leaderboard_id', $leaderboard->id)->delete();
        }
    }

    /**
     * Archive leaderboard entries before reset
     * 
     * @param int $leaderboardId Leaderboard ID
     */
    protected function archiveLeaderboardEntries(int $leaderboardId): void
    {
        // Implementation depends on how you want to archive entries
        // Could create an archive table or add a period_end field to entries
        // For now, we'll just log that archiving would happen here
        \Log::info("Archiving entries for leaderboard ID: {$leaderboardId}");
    }

    /**
     * Check and award trophies based on specific actions
     * 
     * @param int $userId User ID
     * @param string $triggerType Type of action
     * @param int|null $courseId Optional course ID
     * @param array $context Additional context data
     */
    public function checkActionBasedTrophies(int $userId, string $triggerType, ?int $courseId = null, array $context = []): void
    {
        // Find trophies triggered by this action
        $trophyQuery = Trophy::where('trigger_type', $triggerType);

        if ($courseId) {
            $trophyQuery->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global trophies
            });
        } else {
            $trophyQuery->whereNull('course_id'); // Only global trophies
        }

        $trophies = $trophyQuery->get();

        foreach ($trophies as $trophy) {
            // Check if trophy requirements are met based on context
            if ($this->checkTrophyRequirements($trophy, $context)) {
                $this->awardTrophy(
                    $userId,
                    $trophy->id,
                    $courseId,
                    json_encode($context)
                );
            }
        }
    }

    /**
     * Check if trophy requirements are met
     * 
     * @param Trophy $trophy Trophy model
     * @param array $context Context data
     * @return bool True if requirements are met
     */
    protected function checkTrophyRequirements(Trophy $trophy, array $context): bool
    {
        $requirements = json_decode($trophy->requirements, true) ?? [];

        // If no specific requirements, assume it's just triggered by the action
        if (empty($requirements)) {
            return true;
        }

        // Check each requirement against the context
        foreach ($requirements as $key => $value) {
            if (!isset($context[$key]) || $context[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's trophies
     * 
     * @param int $userId User ID
     * @param int|null $courseId Optional course ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTrophies(int $userId, ?int $courseId = null)
    {
        $query = UserTrophy::with('trophy')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($courseId) {
            $query->where(function ($q) use ($courseId) {
                $q->where('course_id', $courseId)
                    ->orWhereNull('course_id'); // Include global trophies
            });
        }

        return $query->get();
    }

    /**
     * Get user's points
     * 
     * @param int $userId User ID
     * @param int|null $courseId Optional course ID
     * @param string|null $period Optional period (daily, weekly, monthly, yearly)
     * @return int Total points
     */
    public function getUserPoints(int $userId, ?int $courseId = null, ?string $period = null): int
    {
        $query = UserPoint::where('user_id', $userId);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        if ($period) {
            $now = Carbon::now();
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

        return $query->sum('points');
    }

    /**
     * Get leaderboard entries
     * 
     * @param int $leaderboardId Leaderboard ID
     * @param int $limit Number of entries to return
     * @param int|null $userId Optional user ID to include in results
     * @return array Leaderboard entries and user's rank
     */
    public function getLeaderboardEntries(int $leaderboardId, int $limit = 10, ?int $userId = null): array
    {
        // Get top entries
        $entries = LeaderboardEntry::with('user:id,name')
            ->where('leaderboard_id', $leaderboardId)
            ->orderBy('rank')
            ->limit($limit)
            ->get();

        $userEntry = null;

        // Get user's entry if requested and not in top entries
        if ($userId) {
            $userEntry = LeaderboardEntry::with('user:id,name')
                ->where('leaderboard_id', $leaderboardId)
                ->where('user_id', $userId)
                ->first();

            // Check if user entry is already in top entries
            $userInTopEntries = $entries->contains(function ($entry) use ($userId) {
                return $entry->user_id === $userId;
            });

            // If user not in top entries but has an entry, add it separately
            if (!$userInTopEntries && $userEntry) {
                $userEntry->is_current_user = true;
            } else {
                $userEntry = null; // Don't duplicate
            }
        }

        return [
            'entries' => $entries,
            'user_entry' => $userEntry
        ];
    }
}
