<?php

namespace App\Services;

use App\Models\Trophy;
use App\Models\User;
use App\Models\UserTrophy;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrophyService
{
    /**
     * Check and award trophies based on a specific trigger type
     *
     * @param string $triggerType The type of trigger (e.g., 'completed_lesson', 'quiz_score')
     * @param User $user The user to check for trophy eligibility
     * @param array $context Additional context data for the trigger
     * @return array Array of awarded trophies
     */
    public function checkAndAwardTrophies(string $triggerType, User $user, array $context = []): array
    {
        $awardedTrophies = [];
        $courseId = $context['course_id'] ?? null;
        
        // Get eligible trophies for this trigger type
        $query = Trophy::where('trigger_type', $triggerType)
            ->where('is_active', true);
            
        // If course-specific context is provided, get both course-specific and global trophies
        if ($courseId) {
            $query->where(function($q) use ($courseId) {
                $q->where('course_id', $courseId)
                  ->orWhereNull('course_id');
            });
        } else {
            $query->whereNull('course_id');
        }
        
        $eligibleTrophies = $query->get();
        
        foreach ($eligibleTrophies as $trophy) {
            // Skip if user already has this trophy (unless it's allowed to be earned multiple times)
            $existingTrophy = UserTrophy::where('user_id', $user->id)
                ->where('trophy_id', $trophy->id)
                ->first();
                
            if ($existingTrophy) {
                continue;
            }
            
            // Check if the user meets the requirements for this trophy
            if ($this->userMeetsTrophyRequirements($user, $trophy, $context)) {
                // Award the trophy
                $userTrophy = $this->awardTrophy($user, $trophy, $courseId, $context);
                if ($userTrophy) {
                    $awardedTrophies[] = $userTrophy;
                }
            }
        }
        
        return $awardedTrophies;
    }
    
    /**
     * Check if a user meets the requirements for a specific trophy
     *
     * @param User $user The user to check
     * @param Trophy $trophy The trophy to check requirements for
     * @param array $context Additional context data
     * @return bool Whether the user meets the requirements
     */
    protected function userMeetsTrophyRequirements(User $user, Trophy $trophy, array $context = []): bool
    {
        $courseId = $context['course_id'] ?? null;
        $count = 0;
        
        switch ($trophy->trigger_type) {
            case 'completed_lesson':
                // Count completed lessons (possibly within a specific course)
                $query = DB::table('learner_progress')
                    ->where('user_id', $user->id)
                    ->where('is_completed', true);
                    
                if ($courseId && $trophy->course_id) {
                    $query->join('lessons', 'learner_progress.lesson_id', '=', 'lessons.id')
                          ->join('levels', 'lessons.level_id', '=', 'levels.id')
                          ->where('levels.course_id', $courseId);
                }
                
                $count = $query->count();
                break;
                
            case 'quiz_score':
                // Check for quiz scores above a threshold
                $threshold = $trophy->points_threshold ?? 90; // Default to 90% if not specified
                $query = DB::table('exam_attempts')
                    ->where('user_id', $user->id)
                    ->where('percentage', '>=', $threshold);
                    
                if ($courseId && $trophy->course_id) {
                    $query->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
                          ->where('exams.course_id', $courseId);
                }
                
                $count = $query->count();
                break;
                
            case 'level_completed':
                // Count completed levels
                $query = DB::table('course_enrollments')
                    ->where('user_id', $user->id)
                    ->where('is_completed', true);
                    
                if ($courseId && $trophy->course_id) {
                    $query->where('course_id', $courseId);
                }
                
                $count = $query->count();
                break;
                
            case 'course_completed':
                // Count completed courses
                $count = DB::table('course_enrollments')
                    ->where('user_id', $user->id)
                    ->where('is_completed', true)
                    ->count();
                break;
                
            case 'term_mastered':
                // Count mastered terms
                $query = DB::table('mastery_progress')
                    ->where('user_id', $user->id)
                    ->where('mastery_level', '>=', 5); // Assuming level 5+ is mastery
                    
                if ($courseId && $trophy->course_id) {
                    $query->join('terms', 'mastery_progress.term_id', '=', 'terms.id')
                          ->where('terms.course_id', $courseId);
                }
                
                $count = $query->count();
                break;
                
            case 'streak':
                // Check login streak from user_points table
                // This is a simplified implementation and might need to be adjusted
                $count = $user->streak_days ?? 0;
                break;
                
            default:
                // For custom triggers, check the context data
                if (isset($context['count'])) {
                    $count = $context['count'];
                }
                break;
        }
        
        // Check if the count meets or exceeds the required repeat count
        return $count >= $trophy->trigger_repeat_count;
    }
    
    /**
     * Award a trophy to a user
     *
     * @param User $user The user to award the trophy to
     * @param Trophy $trophy The trophy to award
     * @param int|null $courseId The course ID if applicable
     * @param array $context Additional context data
     * @return UserTrophy|null The created UserTrophy or null if failed
     */
    protected function awardTrophy(User $user, Trophy $trophy, ?int $courseId = null, array $context = []): ?UserTrophy
    {
        try {
            // Create the user trophy record
            $userTrophy = UserTrophy::create([
                'user_id' => $user->id,
                'trophy_id' => $trophy->id,
                'course_id' => $courseId,
                'context' => $context,
            ]);
            
            // Award points if applicable
            if ($trophy->points > 0) {
                $user->userPoints()->create([
                    'course_id' => $courseId,
                    'type' => 'trophy_earned',
                    'points' => $trophy->points,
                    'description' => 'Earned trophy: ' . $trophy->getTranslation('name', 'en'),
                ]);
            }
            
            return $userTrophy;
        } catch (\Exception $e) {
            Log::error('Failed to award trophy: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trophy_id' => $trophy->id,
                'course_id' => $courseId,
            ]);
            
            return null;
        }
    }
    
    /**
     * Get all trophies earned by a user
     *
     * @param User $user The user to get trophies for
     * @param int|null $courseId Optional course ID to filter by
     * @return \Illuminate\Database\Eloquent\Collection Collection of UserTrophy models
     */
    public function getUserTrophies(User $user, ?int $courseId = null)
    {
        $query = UserTrophy::with('trophy')
            ->where('user_id', $user->id);
            
        if ($courseId) {
            $query->where(function($q) use ($courseId) {
                $q->where('course_id', $courseId)
                  ->orWhereNull('course_id');
            });
        }
        
        return $query->get();
    }
    
    /**
     * Get trophy statistics for a specific trophy
     *
     * @param Trophy $trophy The trophy to get statistics for
     * @return array Trophy statistics
     */
    public function getTrophyStats(Trophy $trophy): array
    {
        $totalUsers = User::count();
        $awardedCount = UserTrophy::where('trophy_id', $trophy->id)->count();
        $percentage = $totalUsers > 0 ? round(($awardedCount / $totalUsers) * 100, 2) : 0;
        
        return [
            'total_users' => $totalUsers,
            'awarded_count' => $awardedCount,
            'percentage' => $percentage,
        ];
    }
}