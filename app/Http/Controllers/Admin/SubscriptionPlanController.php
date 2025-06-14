<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Level;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SubscriptionPlanController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('permission:view.payment', ['only' => ['index', 'show']]);
        $this->middleware('permission:configure.pricing', ['only' => ['store', 'update', 'destroy']]);
    }

    /**
     * Display a listing of the subscription plans.
     */
    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('view.subscription')) {
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
        $sortBy = $request->get('sortBy', 'created_at');
        $orderBy = $request->get('orderBy', 'desc');

        $query->orderBy($sortBy, $orderBy);

        // Include relationships
        $query->with('course');

        // Apply pagination
        $perPage = $request->get('perPage', 15);
        $plans = $query->paginate($perPage);

        return response()->json([
            'plans' => $plans->items(),
            'totalPlans' => $plans->total(),
            'currentPage' => $plans->currentPage(),
            'perPage' => $plans->perPage(),
            'lastPage' => $plans->lastPage(),
        ]);
    }

    /**
     * Store a newly created subscription plan in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!Gate::allows('create.subscription')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|string|in:monthly,quarterly,yearly,one-time',
            'plan_type' => 'required|string|in:recurring,one-time,free',
            'is_free' => 'boolean',
            'accessible_levels' => 'nullable|array',
            'accessible_levels.*' => 'exists:levels,id',
            'duration_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle the case where plan_type is free
        if ($request->plan_type === 'free') {
            $request->merge(['is_free' => true, 'price' => 0]);
        }

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

        $plan = SubscriptionPlan::create($request->all());

        return response()->json($plan, 201);
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if (!Gate::allows('view.subscription')) {
            abort(403);
        }

        $subscriptionPlan->load('course');

        return response()->json($subscriptionPlan);
    }

    /**
     * Update the specified subscription plan in storage.
     */
    public function update(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if (!Gate::allows('edit.subscription')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'course_id' => 'exists:courses,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'string|size:3',
            'billing_cycle' => 'string|in:monthly,quarterly,yearly,one-time',
            'plan_type' => 'string|in:recurring,one-time,free',
            'is_free' => 'boolean',
            'accessible_levels' => 'nullable|array',
            'accessible_levels.*' => 'exists:levels,id',
            'duration_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle the case where plan_type is free
        if ($request->has('plan_type') && $request->plan_type === 'free') {
            $request->merge(['is_free' => true, 'price' => 0]);
        }

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

        $subscriptionPlan->update($request->all());

        return response()->json($subscriptionPlan);
    }

    /**
     * Remove the specified subscription plan from storage.
     */
    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        if (!Gate::allows('delete.subscription')) {
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
        if (!Gate::allows('view.subscription')) {
            abort(403);
        }

        $levels = $course->levels()->select('id', 'title', 'sort_order')->get();

        return response()->json($levels);
    }
}
