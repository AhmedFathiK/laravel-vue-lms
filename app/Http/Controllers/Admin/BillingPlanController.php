<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBillingPlanRequest;
use App\Http\Requests\Admin\UpdateBillingPlanRequest;
use App\Http\Requests\Admin\IndexBillingPlanRequest;
use App\Http\Requests\Admin\ToggleBillingPlanStatusRequest;
use App\Models\Course;
use App\Models\BillingPlan;
use App\Models\PlanFeature;
use App\Models\Feature;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use App\Http\Resources\EntitlementPlanResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillingPlanController extends Controller
{
    /**
     * Display a listing of the billing plans.
     */
    public function index(IndexBillingPlanRequest $request, ?Course $course = null): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = BillingPlan::query();

        // If course is provided (nested route), filter by it
        if ($course) {
            $query->whereHas('courses', function ($q) use ($course) {
                $q->where('courses.id', $course->id);
            });
        }

        // Load relationships
        $query->with(['courses', 'planFeatures.feature']);

        // Apply filters using when()
        $query->when(isset($validated['is_active']), function ($q) use ($validated) {
            return $q->where('is_active', $validated['is_active']);
        })
        ->when(isset($validated['billing_type']), function ($q) use ($validated) {
            return $q->where('billing_type', $validated['billing_type']);
        })
        ->when(isset($validated['billing_interval']), function ($q) use ($validated) {
            return $q->where('billing_interval', $validated['billing_interval']);
        })
        ->when(isset($validated['course_ids']), function ($q) use ($validated) {
            return $q->whereHas('courses', function ($sq) use ($validated) {
                $sq->whereIn('courses.id', $validated['course_ids']);
            });
        })
        ->when(isset($validated['feature_ids']), function ($q) use ($validated) {
            return $q->whereHas('planFeatures', function ($sq) use ($validated) {
                $sq->whereIn('feature_id', $validated['feature_ids']);
            });
        })
        ->when(isset($validated['search']), function ($q) use ($validated) {
            $search = $validated['search'];
            return $q->where(function ($sq) use ($search) {
                $sq->where('name', 'LIKE', "%{$search}%")
                   ->orWhere('description', 'LIKE', "%{$search}%");
            });
        });

        // Apply sorting
        if (isset($validated['sort_by']) && is_array($validated['sort_by'])) {
            foreach ($validated['sort_by'] as $sortItem) {
                if (isset($sortItem['key'], $sortItem['order']) && in_array(strtolower($sortItem['order']), ['asc', 'desc'])) {
                    $key = \Illuminate\Support\Str::snake($sortItem['key']);
                    
                    // Only sort if the column exists in the table
                    if (\Illuminate\Support\Facades\Schema::hasColumn('billing_plans', $key)) {
                        $query->orderBy($key, $sortItem['order']);
                    }
                }
            }
        } else {
            $query->latest();
        }

        $perPage = $validated['items_per_page'] ?? 10;
        
        $plans = $query->paginate($perPage);
        $plans->getCollection()->load(['courses', 'planFeatures.feature']);

        return EntitlementPlanResource::collection($plans);
    }

    /**
     * Toggle the status of a billing plan.
     */
    public function toggleStatus(ToggleBillingPlanStatusRequest $request, $courseOrPlan, $planOrNull = null): EntitlementPlanResource
    {
        $billingPlan = $planOrNull ?: $courseOrPlan;

        if (!($billingPlan instanceof BillingPlan)) {
            $billingPlan = BillingPlan::findOrFail($billingPlan);
        }
        
        $billingPlan->update([
            'is_active' => $request->is_active,
        ]);

        $billingPlan->load(['courses', 'planFeatures.feature']);

        return new EntitlementPlanResource($billingPlan);
    }

    /**
     * Store a newly created billing plan in storage.
     */
    public function store(StoreBillingPlanRequest $request, ?Course $course = null): EntitlementPlanResource
    {
        $billingData = $request->validated();

        // Determine the courses
        $courseIds = [];
        if ($course) {
            $courseIds = [$course->id];
        } else {
            $courseIds = $billingData['course_ids'] ?? [];
        }
        
        if (empty($courseIds)) {
            abort(422, 'At least one course is required.');
        }

        // Calculate access type based on business rules
        $billingData['access_type'] = ($billingData['billing_type'] ?? 'recurring') === 'recurring' ? 'while_active' : 'lifetime';

        if (($billingData['billing_type'] ?? '') === 'free') {
            $billingData['access_type'] = 'lifetime';
        }
        
        if (($billingData['billing_type'] ?? '') === 'one-time' && !empty($billingData['access_duration_days'])) {
            $billingData['access_type'] = 'fixed';
        }

        if (isset($billingData['billing_interval']) && $billingData['billing_interval'] === 'one-time') {
            $billingData['billing_interval'] = null;
        }

        $plan = DB::transaction(function () use ($billingData, $courseIds) {
            $plan = BillingPlan::create($billingData);

            // Link to Courses
            if (!empty($courseIds)) {
                $plan->courses()->sync($courseIds);
            }

            // Sync additional features if provided
            if (isset($billingData['features'])) {
                foreach ($courseIds as $cId) {
                    foreach ($billingData['features'] as $featureId) {
                        PlanFeature::firstOrCreate([
                            'billing_plan_id' => $plan->id,
                            'feature_id' => $featureId,
                            'scope_type' => 'App\Models\Course',
                            'scope_id' => $cId,
                        ]);
                    }
                }
            }

            return $plan;
        });

        $plan->load(['courses', 'planFeatures.feature']);

        return new EntitlementPlanResource($plan);
    }

    /**
     * Display the specified billing plan.
     */
    public function show($courseOrPlan, $planOrNull = null): EntitlementPlanResource
    {
        // Handle both nested (Course, BillingPlan) and global (BillingPlan) signatures
        $billingPlan = $planOrNull ?: $courseOrPlan;

        if (!($billingPlan instanceof BillingPlan)) {
            $billingPlan = BillingPlan::with(['courses', 'planFeatures.feature'])->findOrFail($billingPlan);
        } else {
            $billingPlan->load(['courses', 'planFeatures.feature']);
        }

        if (!Gate::allows('view.billing_plans')) {
            abort(403);
        }

        return new EntitlementPlanResource($billingPlan);
    }

    /**
     * Update the specified billing plan in storage.
     */
    public function update(UpdateBillingPlanRequest $request, $courseOrPlan, $planOrNull = null): EntitlementPlanResource
    {
        $billingPlan = $planOrNull ?: $courseOrPlan;
        
        if (!($billingPlan instanceof BillingPlan)) {
            $billingPlan = BillingPlan::findOrFail($billingPlan);
        }

        $course = $planOrNull ? $courseOrPlan : null; // Nested route provides single course context
        
        if ($course && !($course instanceof Course)) {
            $course = Course::findOrFail($course);
        }

        $billingData = $request->validated();
        
        // Calculate access type based on business rules if type or duration changed
        if (isset($billingData['billing_type']) || isset($billingData['access_duration_days'])) {
            $type = $billingData['billing_type'] ?? $billingPlan->billing_type;
            $duration = $billingData['access_duration_days'] ?? $billingPlan->access_duration_days;

            $billingData['access_type'] = $type === 'recurring' ? 'while_active' : 'lifetime';

            if ($type === 'free') {
                $billingData['access_type'] = 'lifetime';
            }
            
            if ($type === 'one-time' && !empty($duration)) {
                $billingData['access_type'] = 'fixed';
            }
        }

        if (isset($billingData['billing_interval']) && $billingData['billing_interval'] === 'one-time') {
            $billingData['billing_interval'] = null;
        }

        $billingPlan = DB::transaction(function () use ($billingData, $billingPlan, $course) {
            $billingPlan->update($billingData);

            // Determine target courses
            $targetCourseIds = [];

            if ($course) {
                if (isset($billingData['course_ids'])) {
                    $targetCourseIds = $billingData['course_ids'];
                } else {
                    // Keep existing
                    $targetCourseIds = $billingPlan->courses()->pluck('courses.id')->toArray();
                }
            } elseif (isset($billingData['course_ids'])) {
                $targetCourseIds = $billingData['course_ids'];
            } else {
                // Retain existing courses if not provided
                $targetCourseIds = $billingPlan->courses()->pluck('courses.id')->toArray();
            }

            // Sync Courses
            $billingPlan->courses()->sync($targetCourseIds);

            // Sync additional features for ALL target courses
            if (isset($billingData['features'])) {
                // Remove all features for target courses (assuming course.access is now separate)
                PlanFeature::where('billing_plan_id', $billingPlan->id)
                    ->where('scope_type', 'App\Models\Course')
                    ->whereIn('scope_id', $targetCourseIds)
                    ->delete();

                // Then add new features
                foreach ($targetCourseIds as $cId) {
                    foreach ($billingData['features'] as $featureId) {
                        PlanFeature::firstOrCreate([
                            'billing_plan_id' => $billingPlan->id,
                            'feature_id' => $featureId,
                            'scope_type' => 'App\Models\Course',
                            'scope_id' => $cId,
                        ]);
                    }
                }
            }

            return $billingPlan;
        });

        $billingPlan->load(['courses', 'planFeatures.feature']);

        return new EntitlementPlanResource($billingPlan);
    }

    /**
     * Remove the specified billing plan from storage.
     */
    public function destroy($courseOrPlan, $planOrNull = null): JsonResponse
    {
        $billingPlan = $planOrNull ?: $courseOrPlan;

        if (!($billingPlan instanceof BillingPlan)) {
            $billingPlan = BillingPlan::findOrFail($billingPlan);
        }

        if (!Gate::allows('delete.billing_plans')) {
            abort(403);
        }

        // Check if the plan has any active entitlements
        // Check UserEntitlements where billing_plan_id matches
        $hasActiveEntitlements = \App\Models\UserEntitlement::where('billing_plan_id', $billingPlan->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveEntitlements) {
            return response()->json([
                'message' => 'Cannot delete a plan with active entitlements',
            ], 422);
        }

        $billingPlan->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all levels for a course.
     */
    public function getLevelsForCourse(Course $course): JsonResponse
    {
        if (!Gate::allows('view.billing_plans')) {
            abort(403);
        }

        $levels = $course->levels()->select('id', 'title', 'sort_order')->get();

        return response()->json($levels);
    }
}
