<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Models\Course;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionPlanController extends Controller
{


    /**
     * Display a listing of the subscription plans.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.subscriptions')) {
            abort(403);
        }

        $query = SubscriptionPlan::query();

        // Apply filters
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('plan_type')) {
            $query->where('plan_type', $request->plan_type);
        }

        if ($request->has('is_free')) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Apply sorting
        if ($request->has('sortBy')) {
            $sortByArray = $request->get('sortBy');
            if (is_array($sortByArray)) {
                foreach ($sortByArray as $sortItem) {
                    if (isset($sortItem['key'], $sortItem['order']) && in_array(strtolower($sortItem['order']), ['asc', 'desc'])) {
                        $query->orderBy($sortItem['key'], $sortItem['order']);
                    }
                }
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Include relationships
        $query->with('course');

        // Apply pagination
        $perPage = $request->get('itemsPerPage', 15);
        $plans = $query->paginate($perPage);

        return response()->json([
            'items' => $plans->items(),
            'total' => $plans->total(),
        ]);
    }

    /**
     * Store a newly created subscription plan in storage.
     */
    public function store(StoreSubscriptionPlanRequest $request, Course $course): JsonResponse
    {
        // Verify that all accessible_levels belong to the specified course
        if ($request->has('accessible_levels') && !empty($request->accessible_levels)) {
            $course = Course::findOrFail($request->course_id);
            $courseLevelIds = $course->levels()->pluck('id')->toArray();

            foreach ($request->accessible_levels as $levelId) {
                if (!in_array($levelId, $courseLevelIds)) {
                    return response()->json([
                        'message' => 'One or more levels do not belong to the specified course',
                    ], 422);
                }
            }
        }

        $validatedData = $request->validated();

        // Enforce one-time billing cycle for one-time plans
        if (isset($validatedData['plan_type']) && $validatedData['plan_type'] === 'one-time') {
            $validatedData['billing_cycle'] = 'one-time';
        }

        $plan = SubscriptionPlan::create($validatedData);

        return response()->json($plan, 201);
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(Course $course, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if (!Gate::allows('view.subscriptions')) {
            abort(403);
        }

        $subscriptionPlan->load('course');

        return response()->json($subscriptionPlan);
    }

    /**
     * Update the specified subscription plan in storage.
     */
    public function update(UpdateSubscriptionPlanRequest $request, Course $course, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        // Verify that all accessible_levels belong to the specified course
        if ($request->has('accessible_levels') && !empty($request->accessible_levels)) {
            $courseId = $request->has('course_id') ? $request->course_id : $subscriptionPlan->course_id;
            $course = Course::findOrFail($courseId);
            $courseLevelIds = $course->levels()->pluck('id')->toArray();

            foreach ($request->accessible_levels as $levelId) {
                if (!in_array($levelId, $courseLevelIds)) {
                    return response()->json([
                        'message' => 'One or more levels do not belong to the specified course',
                    ], 422);
                }
            }
        }

        $validatedData = $request->validated();

        // Enforce one-time billing cycle for one-time plans
        if (isset($validatedData['plan_type']) && $validatedData['plan_type'] === 'one-time') {
            $validatedData['billing_cycle'] = 'one-time';
        }

        $subscriptionPlan->update($validatedData);

        return response()->json($subscriptionPlan);
    }

    /**
     * Remove the specified subscription plan from storage.
     */
    public function destroy(Course $course, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if (!Gate::allows('delete.subscriptions')) {
            abort(403);
        }

        // Check if the plan has any active subscriptions
        if ($subscriptionPlan->subscriptions()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Cannot delete a plan with active subscriptions',
            ], 422);
        }

        $subscriptionPlan->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all levels for a course.
     */
    public function getLevelsForCourse(Course $course): JsonResponse
    {
        if (!Gate::allows('view.subscriptions')) {
            abort(403);
        }

        $levels = $course->levels()->select('id', 'title', 'sort_order')->get();

        return response()->json($levels);
    }
}
