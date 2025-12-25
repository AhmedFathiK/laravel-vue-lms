<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Level;
use App\Models\Lesson;
use App\Services\Payment\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CourseAccessController extends Controller
{
    /**
     * Set a course as free or paid.
     */
    public function setCourseAccessType(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('edit.course')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'is_free' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->is_free) {
            // Ensure free plan exists and is active
            $existingFreePlan = $course->subscriptionPlans()
                ->where('is_free', true)
                ->where('plan_type', 'free')
                ->first();

            if (!$existingFreePlan) {
                $course->subscriptionPlans()->create([
                    'name' => 'Free Access',
                    'description' => 'Free access to all content in this course',
                    'price' => 0,
                    'currency' => Currency::default(),
                    'billing_cycle' => 'one-time',
                    'plan_type' => 'free',
                    'is_free' => true,
                    'is_active' => true,
                ]);
            } else {
                $existingFreePlan->update(['is_active' => true]);
            }
        } else {
            // Disable free plans
            $course->subscriptionPlans()
                ->where('is_free', true)
                ->update(['is_active' => false]);
        }

        // If the course is set to free, we could optionally set all levels and lessons as free too
        if ($request->is_free && $request->has('cascade_free_access') && $request->boolean('cascade_free_access')) {
            $course->levels()->update(['is_free' => true]);

            // Update all lessons in this course
            $levelIds = $course->levels()->pluck('id')->toArray();
            Lesson::whereIn('level_id', $levelIds)->update(['is_free' => true]);
        }

        return response()->json($course);
    }

    /**
     * Set a level as free or paid.
     */
    public function setLevelAccessType(Request $request, Level $level): JsonResponse
    {
        if (!Gate::allows('edit.course')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'is_free' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $level->update([
            'is_free' => $request->is_free,
        ]);

        // If the level is set to free, we could optionally set all lessons as free too
        if ($request->is_free && $request->has('cascade_free_access') && $request->boolean('cascade_free_access')) {
            $level->lessons()->update(['is_free' => true]);
        }

        return response()->json($level);
    }

    /**
     * Set a lesson as free or paid.
     */
    public function setLessonAccessType(Request $request, Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('edit.course')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'is_free' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $lesson->update([
            'is_free' => $request->is_free,
        ]);

        return response()->json($lesson);
    }

    /**
     * Get all free content for a course.
     */
    public function getFreeCourseContent(Course $course): JsonResponse
    {
        if (!Gate::allows('view.courses')) {
            abort(403);
        }

        // Get all free levels
        $freeLevels = $course->levels()
            ->where('is_free', true)
            ->with(['lessons' => function ($query) {
                $query->where('is_free', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Get all free lessons in non-free levels
        $freeLessonsInPaidLevels = $course->levels()
            ->where('is_free', false)
            ->with(['lessons' => function ($query) {
                $query->where('is_free', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($level) {
                return $level->lessons->isNotEmpty();
            });

        return response()->json([
            'is_free_course' => $course->is_free,
            'free_levels' => $freeLevels,
            'free_lessons_in_paid_levels' => $freeLessonsInPaidLevels,
        ]);
    }

    /**
     * Batch update free access for multiple levels or lessons.
     */
    public function batchUpdateFreeAccess(Request $request): JsonResponse
    {
        if (!Gate::allows('edit.course')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'level_ids' => 'array',
            'level_ids.*' => 'exists:levels,id',
            'lesson_ids' => 'array',
            'lesson_ids.*' => 'exists:lessons,id',
            'is_free' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = [];

        // Update levels if provided
        if ($request->has('level_ids') && !empty($request->level_ids)) {
            Level::whereIn('id', $request->level_ids)
                ->update(['is_free' => $request->is_free]);

            $updated['levels'] = Level::whereIn('id', $request->level_ids)->get();

            // If setting levels to free and cascade is enabled, update their lessons too
            if ($request->is_free && $request->has('cascade_free_access') && $request->boolean('cascade_free_access')) {
                Lesson::whereIn('level_id', $request->level_ids)
                    ->update(['is_free' => true]);
            }
        }

        // Update lessons if provided
        if ($request->has('lesson_ids') && !empty($request->lesson_ids)) {
            Lesson::whereIn('id', $request->lesson_ids)
                ->update(['is_free' => $request->is_free]);

            $updated['lessons'] = Lesson::whereIn('id', $request->lesson_ids)->get();
        }

        return response()->json([
            'message' => 'Free access updated successfully',
            'updated' => $updated,
        ]);
    }
}
