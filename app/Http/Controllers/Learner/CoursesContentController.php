<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserEntitlementResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\UserLevelProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Lesson;

use App\Http\Resources\Learner\LessonResource;
use App\Models\UserStudiedLesson;
use App\Models\ExamAttempt;
use App\Services\EntitlementService;
use App\Services\FeatureAccessService;
use App\Http\Resources\Learner\CourseContentResource;
use Illuminate\Support\Facades\Log; // Added Log

class CoursesContentController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
        protected FeatureAccessService $featureAccessService
    ) {}

    /**
     * Display a specific lesson's content.
     */
    public function showLesson(Request $request, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $course = $lesson->level->course;

        // 1. Ensure content is published
        if ($lesson->status !== 'published' || $lesson->level->status !== 'published' || $course->status !== 'published') {
            abort(404);
        }

        $hasFreeAccess = $this->featureAccessService->hasFeatureForCourse($user, 'content.free.access', $course);
        $hasPaidAccess = $this->featureAccessService->hasFeatureForCourse($user, 'content.paid.access', $course);

        $isFreeLessonAccess = $hasFreeAccess && (($lesson->is_free ?? false) || ($lesson->level->is_free ?? false));

        if (!$hasPaidAccess && !$isFreeLessonAccess) {
            return response()->json([
                "error" => "You do not have access to this lesson content.",
                "course_id" => $course->id
            ], 403);
        }

        // 3. Paid Content: Enforce Sequential Access only for paid learners.
        // Free-content access can open free lessons even if the level itself is otherwise locked.
        // Check Level Status first
        $levelProgress = UserLevelProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('level_id', $lesson->level_id)
            ->first();

        $levelStatus = $levelProgress ? $levelProgress->status : null;

        // Single Source of Truth Logic:
        // If progress exists, respect it.
        // If NO progress exists, only Level 1 (sort_order = 1) is unlocked by default.
        // Everything else is locked.

        $isLevelUnlocked = false;

        if ($levelStatus) {
            if (in_array($levelStatus, [UserLevelProgress::STATUS_UNLOCKED, UserLevelProgress::STATUS_SKIPPED, UserLevelProgress::STATUS_COMPLETED, UserLevelProgress::STATUS_IN_PROGRESS])) {
                $isLevelUnlocked = true;
            }
        } else {
            // No progress record. Check if it's the first level.
            // We need to check if this level has the lowest sort_order for this course.
            $firstLevelId = $course->levels()->orderBy('sort_order')->value('id');
            if ($lesson->level_id === $firstLevelId) {
                $isLevelUnlocked = true;
            }
        }

        if ($hasPaidAccess && !$isLevelUnlocked) {
            return response()->json([
                "error" => "This level is locked.",
                "course_id" => $course->id
            ], 403);
        }

        // Enforce sequence using the last unrestricted lesson only.
        // Restricted (paid-locked) lessons are ignored for progression.
        if (!in_array($levelStatus, [UserLevelProgress::STATUS_SKIPPED, UserLevelProgress::STATUS_COMPLETED])) {
            $previousUnrestrictedLesson = Lesson::where('level_id', $lesson->level_id)
                ->where('sort_order', '<', $lesson->sort_order)
                ->where('status', 'published')
                ->where(function ($query) use ($hasPaidAccess, $hasFreeAccess) {
                    if ($hasPaidAccess) {
                        $query->whereRaw('1 = 1');
                    } elseif ($hasFreeAccess) {
                        $query->where('is_free', true)
                            ->orWhereHas('level', function ($levelQuery) {
                                $levelQuery->where('is_free', true);
                            });
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->orderByDesc('sort_order')
                ->first();

            if ($previousUnrestrictedLesson) {
                $isCompleted = UserStudiedLesson::where('user_id', $user->id)
                    ->where('lesson_id', $previousUnrestrictedLesson->id)
                    ->exists();

                if (!$isCompleted) {
                    return response()->json([
                        "error" => "You must complete the previous accessible lesson first.",
                        "previous_lesson_id" => $previousUnrestrictedLesson->id
                    ], 403);
                }
            }
        }

        // Load slides with content
        $lesson->load([
            'slides' => function ($query) {
                $query->orderBy('sort_order')
                    ->with(['question', 'term']);
            }
        ]);

        // Update Enrollment Last Accessed (if exists)
        CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->update(['last_accessed_at' => now()]);

        return response()->json(new LessonResource($lesson));
    }

    /**
     * Display a user's courses content.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        // Check Entitlement
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check Entitlement (Strict)
        $entitlements = $user->entitlements()
            ->active()
            ->whereHas('billingPlan.courses', function ($query) use ($course) {
                $query->where('courses.id', $course->id);
            })
            ->get();

        $hasEntitlement = $entitlements->filter(function ($entitlement) {
            return $entitlement->isActive();
        })->isNotEmpty();

        if (!$hasEntitlement) {
            // Determine the reason for no access
            $reason = 'not_enrolled';
            $entitlement = null;

            // Check if there is ANY entitlement (even inactive)
            $latestEntitlement = $user->entitlements()
                ->whereHas('billingPlan.courses', function ($query) use ($course) {
                    $query->where('courses.id', $course->id);
                })
                ->orderBy('created_at', 'desc')
                ->with('billingPlan')
                ->first();

            if ($latestEntitlement) {
                $entitlement = $latestEntitlement;
                if ($latestEntitlement->status === 'expired') {
                    $reason = 'expired';
                } elseif ($latestEntitlement->status === 'canceled') {
                    $reason = 'canceled';
                } elseif ($latestEntitlement->status === 'past_due') {
                    $reason = 'past_due';
                } elseif ($latestEntitlement->ends_at && $latestEntitlement->ends_at->isPast()) {
                    $reason = 'expired';
                } else {
                    $reason = 'inactive'; // Generic fallback
                }
            }

            return response()->json([
                "error" => "You do not have active access to this course.",
                "reason" => $reason,
                "course" => [
                    "id" => $course->id,
                    "title" => $course->title,
                    "thumbnail" => $course->thumbnail,
                    "description" => $course->description,
                ],
                "entitlement" => $entitlement ? new UserEntitlementResource($entitlement) : null
            ], 403);
        }

        // Update Enrollment Last Accessed (if exists)
        CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->update(['last_accessed_at' => now()]);

        $course->load([
            'levels' => function ($query) {
                $query->where('status', 'published')
                    ->orderBy('sort_order')
                    ->with('currentUserProgress');
            },
            'levels.lessons' => function ($query) {
                $query->where('status', 'published')
                    ->orderBy('sort_order')
                    ->withCount(['studiedBy as is_completed' => function ($query) {
                        $query->where('user_id', Auth::id());
                    }]);
            },
            'levels.exams' => function ($query) {
                $query->where('status', 'published')
                    ->where('is_active', true)
                    ->withCount(['attempts as is_completed' => function ($query) {
                        $query->where('user_id', Auth::id())
                            ->where('is_passed', true);
                    }]);
            },
            'exams' => function ($query) use ($course) {
                $query->where('status', 'published')
                    ->where('is_active', true)
                    ->where(function ($q) use ($course) {
                        $q->where('id', $course->placement_exam_id)
                            ->orWhere('id', $course->final_exam_id);
                    })
                    ->withCount(['attempts as is_completed' => function ($query) {
                        $query->where('user_id', Auth::id())
                            ->where('is_passed', true);
                    }]);
            },
            'finalExam' => function ($query) {
                $query->withCount(['attempts as is_completed' => function ($query) {
                    $query->where('user_id', Auth::id())
                        ->where('is_passed', true);
                }]);
            },
            'placementExam' => function ($query) {
                $query->withCount(['attempts as is_completed' => function ($query) {
                    $query->where('user_id', Auth::id());
                }]);
            }
        ]);

        $courseData = $course->toArray();

        // Course-wide exams
        $placementExam = null;
        $finalExam = null;
        $currentItem = null; // Track the first incomplete unlocked item

        // Track exam IDs we've already added to course-wide sections to avoid duplicates
        $addedExamIds = [];

        // Handle Placement Exam
        $canAccessPlacementTest = $user->active_course_id === $course->id
            && $this->featureAccessService->hasFeatureForCourse($user, 'placement_test.access', $course);

        if ($course->placementExam && $canAccessPlacementTest) {
            $exam = $course->placementExam->toArray();
            $exam['item_type'] = 'exam';
            $exam['completed'] = ($course->placementExam->is_completed ?? 0) > 0;
            $exam['locked'] = false; // Placement exams are never locked

            // Fetch the attempt that determined placement
            $placementAttempt = ExamAttempt::where('user_id', Auth::id())
                ->where('exam_id', $course->placement_exam_id)
                ->whereNotNull('placement_outcome_level_id')
                ->latest()
                ->first();

            if ($placementAttempt) {
                $exam['outcome'] = [
                    'level_id' => $placementAttempt->placement_outcome_level_id,
                    'score' => $placementAttempt->score,
                    'percentage' => $placementAttempt->percentage,
                ];
                // Ensure it's marked as completed if we have an outcome
                $exam['completed'] = true;
            }

            $placementExam = $exam;
            $addedExamIds[] = $course->placement_exam_id;
        }

        // Handle Final Exam
        if ($course->finalExam) {
            $exam = $course->finalExam->toArray();
            $exam['item_type'] = 'exam';
            $exam['completed'] = ($course->finalExam->is_completed ?? 0) > 0;
            // Locked status will be calculated later along with levels
            $finalExam = $exam;
            $addedExamIds[] = $course->final_exam_id;
        }

        $previousCompleted = true; // Used ONLY for lesson sequencing within a level

        // FIX: Ensure we get the ID of the first level correctly
        $firstLevel = $course->levels->first();
        $firstLevelId = $firstLevel ? $firstLevel->id : null;

        // Log::info("First Level ID: " . $firstLevelId);

        $hasFreeAccess = $this->featureAccessService->hasFeatureForCourse($user, 'content.free.access', $course);
        $hasPaidAccess = $this->featureAccessService->hasFeatureForCourse($user, 'content.paid.access', $course);

        // Tracker for sequential unlocking across the entire course.
        // It persists across levels.
        $previousUnrestrictedLessonCompleted = true;

        foreach ($courseData['levels'] as &$level) {
            $items = [];
            $levelExamCompleted = false;

            // Determine Level Status from UserLevelProgress
            $levelStatus = $level['current_user_progress']['status'] ?? null;

            // Log::info("Level ID: {$level['id']}, Status: " . ($levelStatus ?? 'null'));

            // Access Logic:
            // 1. If explicit status exists -> Use it.
            // 2. If NO status exists -> Only unlock if it's the FIRST level.
            // 3. If Level is FREE and User has feature -> Unlock it.

            $isLevelUnlocked = false;

            // (Note: This overrides locked status if we decide free levels are always accessible regardless of previous completion?
            // Usually, yes. Free levels are like "samples".)
            // But wait, if it's the second level and it's free, can they access it without first level?
            // Let's assume Yes for now, or adhere to sequence?
            // If "is_free" means "Preview", then it should be accessible anytime.

            // Check Feature for Free Level
            if (($level['is_free'] ?? false) && $this->featureAccessService->hasFeatureForCourse($user, 'content.free.access', $course)) {
                $isLevelUnlocked = true;
                $levelStatus = UserLevelProgress::STATUS_UNLOCKED; // Treat as unlocked for logic

                // Inject status so frontend knows it is unlocked
                $level['current_user_progress'] = [
                    'status' => UserLevelProgress::STATUS_UNLOCKED
                ];
                $level['currentUserProgress'] = [
                    'status' => UserLevelProgress::STATUS_UNLOCKED
                ];
            }

            if (!$isLevelUnlocked) {
                if ($levelStatus) {
                    if (in_array($levelStatus, [UserLevelProgress::STATUS_UNLOCKED, UserLevelProgress::STATUS_SKIPPED, UserLevelProgress::STATUS_COMPLETED, UserLevelProgress::STATUS_IN_PROGRESS])) {
                        $isLevelUnlocked = true;
                    }
                } else {
                    if ($level['id'] === $firstLevelId || $previousUnrestrictedLessonCompleted) {
                        $isLevelUnlocked = true;

                        // PERSISTENCE FIX: Create the record in DB so it remains unlocked even if sort order changes
                        UserLevelProgress::firstOrCreate(
                            [
                                'user_id' => Auth::id(),
                                'course_id' => $course->id,
                                'level_id' => $level['id']
                            ],
                            [
                                'status' => UserLevelProgress::STATUS_UNLOCKED,
                                'unlocked_at' => now()
                            ]
                        );

                        // Inject status so frontend knows it is unlocked
                        $level['current_user_progress'] = [
                            'status' => UserLevelProgress::STATUS_UNLOCKED
                        ];
                        // Also inject with camelCase to ensure middleware/frontend consistency
                        $level['currentUserProgress'] = [
                            'status' => UserLevelProgress::STATUS_UNLOCKED
                        ];
                        // Update local variable to match downstream logic if needed
                        $levelStatus = UserLevelProgress::STATUS_UNLOCKED;
                    }
                }
            }

            // If level is unlocked, is it "fully open" (skipped/completed)?
            $isFullyOpen = in_array($levelStatus, [UserLevelProgress::STATUS_SKIPPED, UserLevelProgress::STATUS_COMPLETED]);

            // Add lessons
            if (isset($level['lessons'])) {
                foreach ($level['lessons'] as $lesson) {
                    $lesson['type'] = 'lesson';
                    // Determine if lesson is completed or skipped
                    // UserStudiedLesson record or Level SKIPPED status
                    $isCompletedInDb = $lesson['is_completed'] > 0;
                    $lesson['completed'] = $isCompletedInDb || $levelStatus === UserLevelProgress::STATUS_SKIPPED;
                    $items[] = $lesson;
                }
            }

            // Add exams (Level final exam)
            if (isset($level['exams'])) {
                foreach ($level['exams'] as $exam) {
                    $exam['type'] = 'exam';
                    // Determine if exam is completed or skipped
                    $isCompletedInDb = $exam['is_completed'] > 0;
                    $exam['completed'] = $isCompletedInDb || $levelStatus === UserLevelProgress::STATUS_SKIPPED;

                    if ($exam['completed']) {
                        $levelExamCompleted = true;
                    }
                    $items[] = $exam;
                }
            }

            // Apply locked status
            // $previousUnrestrictedLessonCompleted is NOT reset here to maintain cross-level sequence.

            foreach ($items as &$item) {
                $isLesson = ($item['type'] ?? null) === 'lesson';

                // Determine free access for this item.
                // Level-level free inheritance applies to lessons only.
                $isItemFreeByOwnFlag = ($item['is_free'] ?? false) && $hasFreeAccess;
                $isLessonFreeByLevel = $isLesson && ($level['is_free'] ?? false) && $hasFreeAccess;
                $isItemFree = $isItemFreeByOwnFlag || $isLessonFreeByLevel;

                $isRestrictedByPlan = !$hasPaidAccess && !$isItemFree;

                $item['is_paid_locked'] = false;
                $item['lock_reason'] = null;

                if ($isRestrictedByPlan) {
                    // Keep paid content visible, but locked as paid.
                    $item['locked'] = true;
                    $item['is_paid_locked'] = true;
                    $item['lock_reason'] = 'paid';
                } elseif (!$isLevelUnlocked) {
                    // Level is locked -> all items remain locked (even free ones)
                    $item['locked'] = true;
                } elseif ($isFullyOpen) {
                    $item['locked'] = false;
                } else {
                    // Both lessons and exams now follow the sequence
                    $item['locked'] = !$previousUnrestrictedLessonCompleted;
                }

                // Identify the current (first incomplete unlocked) item
                // An item is only eligible for currentItem if it is NOT locked.
                if (!$currentItem && !$item['locked'] && !$item['completed'] && $isLevelUnlocked) {
                    $currentItem = [
                        'level_id' => $level['id'],
                        'item_id' => $item['id'],
                        'type' => $item['type']
                    ];
                }

                // Update sequence tracker for any accessible item (lesson or exam)
                if (!$isRestrictedByPlan) {
                    $previousUnrestrictedLessonCompleted = (bool) $item['completed'];
                }
            }
            unset($item); // Break reference

            $level['items'] = $items;
            unset($level['lessons']);
            unset($level['exams']);
        }
        unset($level);

        // Finally, set the locked status for the course final exam.
        // It should be locked unless ALL levels are completed/skipped?
        // Or strictly sequential?
        // Let's check the LAST level's status.
        if ($finalExam) {
            $lastLevel = end($courseData['levels']);
            $lastLevelStatus = $lastLevel['current_user_progress']['status'] ?? null;

            // Unlock final exam if last level is completed or skipped
            $finalExam['locked'] = !in_array($lastLevelStatus, [UserLevelProgress::STATUS_COMPLETED, UserLevelProgress::STATUS_SKIPPED]);
        }

        $responseData = array_merge($courseData, [
            'placementExam' => $placementExam,
            'finalExam' => $finalExam,
            'currentItem' => $currentItem,
            'entitlement' => $entitlements->first() ? new \App\Http\Resources\UserEntitlementResource($entitlements->first()) : null,
        ]);

        return response()->json(new CourseContentResource($responseData));
    }
}
