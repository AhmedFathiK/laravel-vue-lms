<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class FeatureAccessService
{
    /**
     * Check if the user has access to a specific feature.
     * 
     * @param User $user The user to check.
     * @param string $featureCode The code of the feature (e.g., 'revision.access').
     * @param string|null $scopeType The type of the scope (e.g., 'App\Models\Course').
     * @param int|null $scopeId The ID of the scope (e.g., course ID).
     * @return bool
     */
    public function hasFeature(User $user, string $featureCode, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        return $user->hasCapability($featureCode, $scopeType, $scopeId);
    }

    /**
     * Check if the user has access to a specific feature within the context of a specific course.
     * This is a convenience method for course-scoped features.
     * 
     * @param User $user The user to check.
     * @param string $featureCode The code of the feature.
     * @param int|Course $course The course object or ID.
     * @return bool
     */
    public function hasFeatureForCourse(User $user, string $featureCode, int|Course $course): bool
    {
        $courseId = $course instanceof Course ? $course->id : $course;
        return $this->hasFeature($user, $featureCode, 'App\Models\Course', $courseId);
    }

    /**
     * Check if the authenticated user has access to a feature for their currently active course.
     * 
     * @param string $featureCode
     * @return bool
     */
    public function hasFeatureForActiveCourse(string $featureCode): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Ensure active course is loaded/set
        if (!$user->active_course_id) {
            return false;
        }

        return $this->hasFeatureForCourse($user, $featureCode, $user->active_course_id);
    }
}
