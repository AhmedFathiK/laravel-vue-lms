<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStreak;
use Carbon\Carbon;

class StreakService
{
    /**
     * Update the user's streak for the given course.
     */
    public function updateStreak(User $user, int $courseId): void
    {
        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $streak = UserStreak::firstOrNew([
            'user_id' => $user->id,
            'course_id' => $courseId
        ]);

        if (!$streak->exists) {
            $streak->current_streak = 1;
            $streak->last_activity_date = $today;
            $streak->save();
            return;
        }

        // If activity already recorded today, do nothing
        if ($streak->last_activity_date === $today) {
            return;
        }

        // If activity was yesterday, increment streak
        if ($streak->last_activity_date === $yesterday) {
            $streak->current_streak++;
        } else {
            // Streak broken, reset to 1 (since they were active today)
            $streak->current_streak = 1;
        }

        $streak->last_activity_date = $today;
        $streak->save();
    }
}
