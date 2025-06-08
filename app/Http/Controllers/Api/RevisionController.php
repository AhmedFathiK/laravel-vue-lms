<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RevisionRequest;
use App\Models\Concept;
use App\Models\MasteryProgress;
use App\Models\RevisionItem;
use App\Models\Term;
use App\Services\FSRSService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RevisionController extends Controller
{
    protected FSRSService $fsrsService;

    public function __construct(FSRSService $fsrsService)
    {
        $this->fsrsService = $fsrsService;
    }

    /**
     * Get all revision items for the authenticated user
     */
    public function index(RevisionRequest $request): JsonResponse
    {
        $query = RevisionItem::where('user_id', Auth::id())
            ->with('revisionable')
            ->orderBy('due_date', 'asc');

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
     * Get items due for revision
     */
    public function getDueItems(RevisionRequest $request): JsonResponse
    {
        $limit = $request->input('limit', 20);
        $query = RevisionItem::where('user_id', Auth::id())
            ->with('revisionable')
            ->where('due_date', '<=', now())
            ->orderBy('due_date', 'asc');

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

        $dueItems = $query->paginate($limit);

        return response()->json($dueItems);
    }

    /**
     * Add a term or concept to the user's revision items
     */
    public function addItem(RevisionRequest $request): JsonResponse
    {
        $type = $request->type === 'term' ? Term::class : Concept::class;
        $model = $request->type === 'term' ? Term::find($request->id) : Concept::find($request->id);

        if (!$model) {
            return response()->json(['message' => 'Item not found'], 404);
        }

        // Check if the item is already in the user's revision list
        $existingItem = RevisionItem::where('user_id', Auth::id())
            ->where('revisionable_type', $type)
            ->where('revisionable_id', $request->id)
            ->first();

        if ($existingItem) {
            return response()->json(['message' => 'Item already in revision list', 'item' => $existingItem]);
        }

        // Create a new revision item
        $revisionItem = new RevisionItem([
            'user_id' => Auth::id(),
            'state' => 'new',
            'due_date' => now(),
        ]);

        $model->revisionItems()->save($revisionItem);

        return response()->json([
            'message' => 'Item added to revision list',
            'item' => $revisionItem->load('revisionable')
        ], 201);
    }

    /**
     * Record a review response for a revision item
     */
    public function recordResponse(RevisionRequest $request, RevisionItem $revisionItem): JsonResponse
    {
        // Ensure the revision item belongs to the authenticated user
        if ($revisionItem->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Apply FSRS algorithm to update the revision item
        if ($revisionItem->review_count === 0) {
            $this->fsrsService->initializeRevisionItem($revisionItem, $request->grade);
        } else {
            $this->fsrsService->updateRevisionItem($revisionItem, $request->grade);
        }

        // Save the updated revision item
        $revisionItem->save();

        // Process mastery progress if provided
        if ($request->has('mastery_progress')) {
            $now = now();

            foreach ($request->mastery_progress as $progressData) {
                // Create or update mastery progress
                MasteryProgress::updateOrCreate(
                    [
                        'user_id' => Auth::id(),
                        'revision_item_id' => $revisionItem->id,
                        'category' => $progressData['category'],
                    ],
                    [
                        'description' => $progressData['description'] ?? null,
                        'strength' => $progressData['strength'] ?? 1,
                        'last_identified_at' => $now,
                    ]
                );
            }
        }

        // Get next intervals for reference
        $nextIntervals = $this->fsrsService->getNextIntervals($revisionItem);

        return response()->json([
            'message' => 'Response recorded successfully',
            'item' => $revisionItem->load('revisionable'),
            'next_intervals' => $nextIntervals
        ]);
    }

    /**
     * Get mastery progress for a user
     */
    public function getMasteryProgress(RevisionRequest $request): JsonResponse
    {
        $query = MasteryProgress::where('user_id', Auth::id())
            ->with('revisionItem.revisionable')
            ->orderBy('strength', 'asc')
            ->orderBy('last_identified_at', 'desc');

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by strength
        if ($request->has('strength_below')) {
            $query->where('strength', '<', $request->strength_below);
        }

        // Filter by course
        if ($request->has('course_id')) {
            $courseId = $request->course_id;
            $query->whereHas('revisionItem', function ($q) use ($courseId) {
                $q->whereHasMorph(
                    'revisionable',
                    [Term::class, Concept::class],
                    function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    }
                );
            });
        }

        $masteryProgress = $query->paginate(20);

        return response()->json($masteryProgress);
    }

    /**
     * Generate practice questions for revision
     */
    public function generatePractice(RevisionRequest $request): JsonResponse
    {
        $count = $request->input('count', 5);
        $includeMasteryProgress = $request->boolean('include_mastery_progress', true);
        $type = $request->input('type', 'both');

        // Base query for due revision items
        $query = RevisionItem::where('user_id', Auth::id())
            ->with('revisionable')
            ->where('due_date', '<=', now());

        // Filter by course if specified
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

        // Filter by type if specified
        if ($type !== 'both') {
            $modelType = $type === 'term' ? Term::class : Concept::class;
            $query->where('revisionable_type', $modelType);
        }

        // Get items with lowest retrievability first
        $items = $query->orderBy('retrievability', 'asc')->limit($count)->get();

        // If we need more items and mastery progress should be included
        if ($items->count() < $count && $includeMasteryProgress) {
            $neededCount = $count - $items->count();

            // Get items with mastery progress that aren't already in our list
            $progressItemIds = MasteryProgress::where('user_id', Auth::id())
                ->select('revision_item_id')
                ->distinct()
                ->pluck('revision_item_id')
                ->toArray();

            $additionalQuery = RevisionItem::where('user_id', Auth::id())
                ->with('revisionable')
                ->whereIn('id', $progressItemIds)
                ->whereNotIn('id', $items->pluck('id')->toArray());

            // Apply same course filter if needed
            if ($request->has('course_id')) {
                $courseId = $request->course_id;
                $additionalQuery->whereHasMorph(
                    'revisionable',
                    [Term::class, Concept::class],
                    function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    }
                );
            }

            // Apply type filter if needed
            if ($type !== 'both') {
                $modelType = $type === 'term' ? Term::class : Concept::class;
                $additionalQuery->where('revisionable_type', $modelType);
            }

            $additionalItems = $additionalQuery->orderBy('due_date', 'asc')->limit($neededCount)->get();
            $items = $items->merge($additionalItems);
        }

        // Generate practice questions for each item
        $practiceQuestions = [];
        foreach ($items as $item) {
            $revisionable = $item->revisionable;

            // Generate different question types based on whether it's a term or concept
            if ($item->revisionable_type === Term::class) {
                // For terms, create definition/translation questions
                $practiceQuestions[] = [
                    'revision_item_id' => $item->id,
                    'question_type' => 'definition',
                    'prompt' => "What is the definition of '{$revisionable->term}'?",
                    'answer' => $revisionable->definition,
                    'item_data' => [
                        'term' => $revisionable->term,
                        'definition' => $revisionable->definition,
                        'translation' => $revisionable->translation,
                        'media_url' => $revisionable->media_url,
                        'media_type' => $revisionable->media_type,
                    ]
                ];
            } else {
                // For concepts, create explanation questions
                $practiceQuestions[] = [
                    'revision_item_id' => $item->id,
                    'question_type' => 'explanation',
                    'prompt' => "Explain the concept: '{$revisionable->title}'",
                    'answer' => $revisionable->explanation,
                    'item_data' => [
                        'title' => $revisionable->title,
                        'explanation' => $revisionable->explanation,
                        'examples' => $revisionable->examples,
                        'type' => $revisionable->type,
                        'media_url' => $revisionable->media_url,
                        'media_type' => $revisionable->media_type,
                    ]
                ];
            }
        }

        return response()->json([
            'practice_questions' => $practiceQuestions,
            'count' => count($practiceQuestions)
        ]);
    }

    /**
     * Get statistics about the user's revision progress
     */
    public function getStatistics(RevisionRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        // Base query
        $query = RevisionItem::where('user_id', $userId);

        // Filter by course if specified
        if ($courseId) {
            $query->whereHasMorph(
                'revisionable',
                [Term::class, Concept::class],
                function ($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                }
            );
        }

        // Get counts by state
        $stateCounts = $query->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();

        // Get due items count
        $dueCount = $query->where('due_date', '<=', now())->count();

        // Get review count by day for the last 30 days
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $reviewsByDay = $query->where('last_review', '>=', $thirtyDaysAgo)
            ->select(DB::raw('DATE(last_review) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Get mastery progress statistics
        $progressQuery = MasteryProgress::where('user_id', $userId);

        if ($courseId) {
            $progressQuery->whereHas('revisionItem', function ($q) use ($courseId) {
                $q->whereHasMorph(
                    'revisionable',
                    [Term::class, Concept::class],
                    function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    }
                );
            });
        }

        $progressByCategory = $progressQuery->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->pluck('count', 'category')
            ->toArray();

        return response()->json([
            'total_items' => $query->count(),
            'state_counts' => $stateCounts,
            'due_count' => $dueCount,
            'reviews_by_day' => $reviewsByDay,
            'mastery_progress' => [
                'total' => array_sum($progressByCategory),
                'by_category' => $progressByCategory
            ]
        ]);
    }
}
