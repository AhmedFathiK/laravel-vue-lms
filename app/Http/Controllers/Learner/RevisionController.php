<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learner\RevisionRequest;
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

        // Get items with lowest stability first
        $query->orderBy('stability', 'asc')
            ->orderBy('due_date', 'asc');

        $items = $query->take($count)->get();

        // If we don't have enough items, try to get some that aren't due yet
        if ($items->count() < $count) {
            $remainingCount = $count - $items->count();

            $notDueQuery = RevisionItem::where('user_id', Auth::id())
                ->with('revisionable')
                ->where('due_date', '>', now());

            // Apply same filters
            if ($request->has('course_id')) {
                $courseId = $request->course_id;
                $notDueQuery->whereHasMorph(
                    'revisionable',
                    [Term::class, Concept::class],
                    function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    }
                );
            }

            if ($type !== 'both') {
                $modelType = $type === 'term' ? Term::class : Concept::class;
                $notDueQuery->where('revisionable_type', $modelType);
            }

            // Order by due date to get the ones that will be due soonest
            $notDueQuery->orderBy('due_date', 'asc');

            $notDueItems = $notDueQuery->take($remainingCount)->get();

            // Merge the collections
            $items = $items->merge($notDueItems);
        }

        // Prepare the response data
        $practiceItems = [];

        foreach ($items as $item) {
            $practiceItem = [
                'revision_item' => $item,
                'revisionable' => $item->revisionable,
            ];

            // Include mastery progress if requested
            if ($includeMasteryProgress) {
                $masteryProgress = MasteryProgress::where('user_id', Auth::id())
                    ->where('revision_item_id', $item->id)
                    ->orderBy('strength', 'asc')
                    ->get();

                $practiceItem['mastery_progress'] = $masteryProgress;
            }

            $practiceItems[] = $practiceItem;
        }

        return response()->json([
            'practice_items' => $practiceItems,
            'count' => count($practiceItems)
        ]);
    }

    /**
     * Get statistics about the user's revision progress
     */
    public function getStatistics(RevisionRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $courseId = $request->input('course_id');

        // Base query for user's revision items
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

        // Total items
        $totalItems = $query->count();

        // Items by state
        $itemsByState = $query->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();

        // Fill in missing states with zero
        $states = ['new', 'learning', 'review', 'mastered'];
        foreach ($states as $state) {
            if (!isset($itemsByState[$state])) {
                $itemsByState[$state] = 0;
            }
        }

        // Due items
        $dueItems = $query->where('due_date', '<=', now())->count();

        // Items due in the next 7 days
        $now = now();
        $nextWeek = now()->addDays(7);
        $upcomingItems = $query->whereBetween('due_date', [$now, $nextWeek])->count();

        // Average stability
        $avgStability = $query->where('stability', '>', 0)->avg('stability') ?? 0;

        // Average difficulty
        $avgDifficulty = $query->where('difficulty', '>', 0)->avg('difficulty') ?? 0;

        // Review history (last 30 days)
        $thirtyDaysAgo = now()->subDays(30);
        $reviewHistory = DB::table('revision_item_histories')
            ->join('revision_items', 'revision_item_histories.revision_item_id', '=', 'revision_items.id')
            ->where('revision_items.user_id', $userId)
            ->where('revision_item_histories.created_at', '>=', $thirtyDaysAgo)
            ->select(
                DB::raw('DATE(revision_item_histories.created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing dates with zero
        $dateRange = [];
        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            if (!isset($reviewHistory[$date])) {
                $reviewHistory[$date] = 0;
            }
            $dateRange[] = $date;
        }

        // Sort by date
        ksort($reviewHistory);

        // Mastery progress statistics
        $masteryQuery = MasteryProgress::where('user_id', $userId);

        if ($courseId) {
            $masteryQuery->whereHas('revisionItem', function ($q) use ($courseId) {
                $q->whereHasMorph(
                    'revisionable',
                    [Term::class, Concept::class],
                    function ($q) use ($courseId) {
                        $q->where('course_id', $courseId);
                    }
                );
            });
        }

        $masteryByCategory = $masteryQuery->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $masteryByStrength = $masteryQuery->select(
            DB::raw('FLOOR(strength) as strength_level'),
            DB::raw('count(*) as count')
        )
            ->groupBy('strength_level')
            ->pluck('count', 'strength_level')
            ->toArray();

        return response()->json([
            'total_items' => $totalItems,
            'items_by_state' => $itemsByState,
            'due_items' => $dueItems,
            'upcoming_items' => $upcomingItems,
            'avg_stability' => round($avgStability, 2),
            'avg_difficulty' => round($avgDifficulty, 2),
            'review_history' => $reviewHistory,
            'mastery_by_category' => $masteryByCategory,
            'mastery_by_strength' => $masteryByStrength
        ]);
    }
}
