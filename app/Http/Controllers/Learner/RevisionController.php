<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\RevisionRequest;
use App\Models\Concept;
use App\Models\Course;
use App\Models\RevisionItem;
use App\Models\Term;
use App\Services\FSRSService;
use App\Services\RevisionSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RevisionController extends Controller
{
    protected FSRSService $fsrsService;
    protected RevisionSessionService $revisionSessionService;

    public function __construct(FSRSService $fsrsService, RevisionSessionService $revisionSessionService)
    {
        $this->fsrsService = $fsrsService;
        $this->revisionSessionService = $revisionSessionService;
    }

    /**
     * Get all revision items for the authenticated user
     */
    public function index(RevisionRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Get allowed course IDs based on 'revision.access' capability
        $allowedCourseIds = $user->entitlements()
            ->active()
            ->whereHas('capabilities', function ($q) {
                $q->where('feature_code', 'revision.access')
                    ->where('scope_type', 'App\Models\Course');
            })
            ->with(['capabilities' => function ($q) {
                $q->where('feature_code', 'revision.access')
                    ->where('scope_type', 'App\Models\Course');
            }])
            ->get()
            ->flatMap(function ($entitlement) {
                return $entitlement->capabilities->pluck('scope_id');
            })
            ->unique()
            ->values()
            ->all();

        $query = RevisionItem::where('user_id', $user->id)
            ->with('revisionable')
            ->orderBy('due_date', 'asc');

        // Apply Capability Filter
        $query->whereHasMorph(
            'revisionable',
            [Term::class, Concept::class],
            function ($q) use ($allowedCourseIds) {
                $q->whereIn('course_id', $allowedCourseIds);
            }
        );

        // Filter by state

        // Filter by state
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        // Filter by due status
        if ($request->boolean('due', false)) {
            $query->where('due_date', '<=', now());
        }

        // Filter by revisionable type
        if ($request->has('type')) {
            $type = $request->type === 'term' ? Term::class : Concept::class;
            $query->where('revisionable_type', $type);
        }

        // Filter by course
        if ($request->has('course_id')) {
            $courseId = $request->course_id;
            $query->whereHasMorph(
                'revisionable',
                [Term::class, Concept::class],
                function ($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                }
            );
        }

        // Apply limit
        $limit = $request->input('limit', 20);
        $items = $query->paginate($limit);

        return response()->json($items);
    }

    /**
     * Generate practice questions for revision using the new Session Service
     */
    public function generatePractice(Request $request): JsonResponse
    {
        $user = Auth::user();
        $courseId = $request->input('course_id');
        $course = $courseId ? Course::find($courseId) : null;
        $type = $request->input('type', 'both');
        $limit = $request->input('limit', 20);
        $earlyReview = $request->boolean('early_review');

        $slides = $this->revisionSessionService->generateSession($user, $course, $type, $limit, $earlyReview);

        return response()->json([
            'slides' => $slides,
            'count' => count($slides)
        ]);
    }

    /**
     * Get grammar topics with mastery status for the accordion view
     */
    public function getGrammarTopics(Request $request): JsonResponse
    {
        $userId = Auth::id();
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $courseId = $request->input('course_id');

        // Get allowed course IDs based on 'revision.access' capability
        $allowedCourseIds = $user->entitlements()
            ->active()
            ->whereHas('capabilities', function ($q) {
                $q->where('feature_code', 'revision.access')
                    ->where('scope_type', 'App\Models\Course');
            })
            ->with(['capabilities' => function ($q) {
                $q->where('feature_code', 'revision.access')
                    ->where('scope_type', 'App\Models\Course');
            }])
            ->get()
            ->flatMap(function ($entitlement) {
                return $entitlement->capabilities->pluck('scope_id');
            })
            ->unique()
            ->values()
            ->all();

        // Fetch categories and their concepts
        $query = \App\Models\ConceptCategory::with(['concepts' => function ($q) {
            $q->with('revisionItems'); // Load revision status for concepts
        }]);

        if ($courseId) {
            if (!in_array($courseId, $allowedCourseIds)) {
                return response()->json([]);
            }
            $query->where('course_id', $courseId);
        } else {
            $query->whereIn('course_id', $allowedCourseIds);
        }

        $categories = $query->get();

        $data = $categories->map(function ($category) use ($userId) {
            $concepts = $category->concepts->map(function ($concept) use ($userId) {
                $item = $concept->revisionItems->where('user_id', $userId)->first();

                // Determine status
                $status = 'Not Started';
                $stability = 0;

                if ($item) {
                    $stability = $item->stability;
                    if (in_array($item->state, ['new', 'relearning']) || $item->stability < 2) {
                        $status = 'Needs practice';
                    } elseif ($item->stability >= 2 && $item->stability < 10) {
                        $status = 'Improving';
                    } elseif ($item->stability >= 10 && $item->stability < 50) {
                        $status = 'Strong';
                    } else {
                        $status = 'Mastered';
                    }
                }

                return [
                    'id' => $concept->id,
                    'title' => $concept->title,
                    'explanation' => $concept->explanation,
                    'status' => $status,
                    'stability' => $stability,
                    'lesson_id' => $concept->lesson_id
                ];
            });

            return [
                'id' => $category->id,
                'title' => $category->title,
                'description' => null, // ConceptCategory doesn't have a description field
                'topics_count' => $concepts->count(),
                'topics' => $concepts
            ];
        });

        return response()->json($data);
    }

    /**
     * Record a review response for a revision item (Batch or Single)
     * Now accepts 'results' array for the item's questions
     */
    public function recordResponse(Request $request): JsonResponse
    {
        $request->validate([
            'revision_item_id' => 'required|exists:revision_items,id',
            'results' => 'required|array', // Array of booleans [true, false, true]
        ]);

        $revisionItem = RevisionItem::find($request->revision_item_id);

        // Ensure the revision item belongs to the authenticated user
        if ($revisionItem->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Calculate Grade based on results
        $grade = $this->revisionSessionService->calculateGrade($request->results);

        // Apply FSRS algorithm
        if ($revisionItem->review_count === 0) {
            $this->fsrsService->initializeRevisionItem($revisionItem, $grade);
        } else {
            $this->fsrsService->updateRevisionItem($revisionItem, $grade);
        }

        $revisionItem->save();

        return response()->json([
            'message' => 'Response recorded successfully',
            'grade' => $grade,
            'item' => $revisionItem->load('revisionable'),
            'next_due' => $revisionItem->due_date
        ]);
    }

    /**
     * Get statistics about the user's revision progress (Buckets)
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        // Helper to get stats for a type
        $getStatsForType = function ($typeClass) use ($userId, $courseId) {
            $query = RevisionItem::where('user_id', $userId)
                ->where('revisionable_type', $typeClass);

            if ($courseId) {
                $query->whereHasMorph(
                    'revisionable',
                    [$typeClass],
                    function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    }
                );
            }

            $items = $query->get();

            // Buckets
            $buckets = [
                'needs_practice' => 0,
                'improving' => 0,
                'strong' => 0,
                'mastered' => 0,
            ];

            foreach ($items as $item) {
                // Logic for buckets
                // Needs Practice: New/Relearning or Stability < 2
                if (in_array($item->state, ['new', 'relearning']) || $item->stability < 2) {
                    $buckets['needs_practice']++;
                }
                // Improving: Learning/Review & Stability 2-10
                elseif ($item->stability >= 2 && $item->stability < 10) {
                    $buckets['improving']++;
                }
                // Strong: Stability 10-50
                elseif ($item->stability >= 10 && $item->stability < 50) {
                    $buckets['strong']++;
                }
                // Mastered: Stability >= 50
                else {
                    $buckets['mastered']++;
                }
            }

            return [
                'total' => $items->count(),
                'buckets' => $buckets,
                'due_count' => $items->where('due_date', '<=', now())->count()
            ];
        };

        return response()->json([
            'terms' => $getStatsForType(Term::class),
            'concepts' => $getStatsForType(Concept::class),
        ]);
    }

    /**
     * Initialize revision items for a completed lesson
     * To be called when a lesson is finished
     */
    public function initializeForLesson(Request $request): JsonResponse
    {
        // This endpoint might be called by the frontend after finishing a lesson
        // Or called internally by LessonController

        $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'answers' => 'required|array' // [ 'term_id_1' => correct_count, 'concept_id_2' => correct_count ]
        ]);

        $user = Auth::user();
        $lessonId = $request->lesson_id;
        $answers = $request->answers; // Map of ID => count of correct answers in lesson

        // We assume the frontend calculates how many times each term/concept was answered correctly
        // and sends it here.
        // Or we can calculate it if we have access to the raw lesson attempt data.
        // For simplicity and flexibility, let's assume the client sends the summary.
        // "answers": [ { "type": "term", "id": 1, "correct_count": 2 }, ... ]

        $createdCount = 0;

        foreach ($answers as $answer) {
            $type = $answer['type'] === 'term' ? Term::class : Concept::class;
            $id = $answer['id'];
            $correctCount = $answer['correct_count'];

            // Calculate Initial Grade
            // 0 -> 1, 1 -> 2, 2 -> 3, 3+ -> 4
            $grade = 1;
            if ($correctCount == 1) $grade = 2;
            elseif ($correctCount == 2) $grade = 3;
            elseif ($correctCount >= 3) $grade = 4;

            // Check if exists
            $exists = RevisionItem::where('user_id', $user->id)
                ->where('revisionable_type', $type)
                ->where('revisionable_id', $id)
                ->exists();

            if (!$exists) {
                $item = new RevisionItem([
                    'user_id' => $user->id,
                    'state' => 'new',
                    'due_date' => now(),
                ]);

                $model = $type::find($id);
                if ($model) {
                    $model->revisionItems()->save($item);

                    // Initialize FSRS
                    $this->fsrsService->initializeRevisionItem($item, $grade);
                    $item->save();
                    $createdCount++;
                }
            }
        }

        return response()->json(['message' => "Initialized $createdCount revision items"]);
    }
}
