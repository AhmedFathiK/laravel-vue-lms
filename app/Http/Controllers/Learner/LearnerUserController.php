<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LearnerUserController extends Controller
{
    /**
     * Set the active course for the user.
     */
    public function setActiveCourse(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $courseId = $request->course_id;

        // Check for active entitlement
        $hasEntitlement = $user->entitlements()
            ->active()
            ->whereHas('billingPlan.courses', function ($q) use ($courseId) {
                $q->where('courses.id', $courseId);
            })
            ->exists();

        if (!$hasEntitlement) {
            return response()->json(['error' => 'You do not have active access to this course.'], 403);
        }

        $user->active_course_id = $courseId;
        $user->save();

        return response()->json([
            'message' => 'Active course updated',
            'active_course_id' => $user->active_course_id
        ]);
    }

    /**
     * Update the user's locale preference.
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $user->interface_language = $validated['locale'];
        $user->save();

        return response()->json(['message' => 'Locale updated successfully', 'locale' => $user->interface_language]);
    }
}
