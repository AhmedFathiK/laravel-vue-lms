<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exam\DestroyExamRequest;
use App\Http\Requests\Admin\Exam\IndexExamRequest;
use App\Http\Requests\Admin\Exam\ShowExamRequest;
use App\Http\Requests\Admin\Exam\StoreExamRequest;
use App\Http\Requests\Admin\Exam\UpdateExamRequest;
use App\Models\Course;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    /**
     * Display a listing of the exams.
     */
    public function index(Course $course, IndexExamRequest $request): JsonResponse
    {
        $query = Exam::where('course_id', $course->id);

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_direction', 'desc'));

        // Paginate
        $perPage = $request->get('per_page', 15);
        $exams = $query->paginate($perPage);

        return response()->json($exams);
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(Course $course, StoreExamRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($course, $request) {
            $data = $request->validated();
            $sectionsData = $data['sections'] ?? [];
            unset($data['sections']);

            $data['course_id'] = $course->id;
            $exam = Exam::create($data);

            $this->syncSections($exam, $sectionsData);

            return response()->json([
                'message' => 'Exam created successfully',
                'exam' => $exam->load(['sections.examQuestions'])
            ], 201);
        });
    }

    /**
     * Display the specified exam.
     */
    public function show(Course $course, Exam $exam, ShowExamRequest $request): JsonResponse
    {
        // Load sections with canonical questions and their context
        $exam->load(['sections.questions.context']);

        return response()->json(new \App\Http\Resources\Admin\ExamResource($exam));
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(Course $course, UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        return DB::transaction(function () use ($request, $exam) {
            $data = $request->validated();
            $sectionsData = $data['sections'] ?? [];
            unset($data['sections']);

            $exam->update($data);

            $this->syncSections($exam, $sectionsData);

            return response()->json([
                'message' => 'Exam updated successfully',
                'exam' => $exam->fresh()->load(['sections.questions'])
            ]);
        });
    }

    /**
     * Sync sections and their questions for an exam.
     */
    private function syncSections(Exam $exam, array $sectionsData): void
    {
        $sectionIds = [];
        $allQuestionIds = [];

        foreach ($sectionsData as $index => $sectionData) {
            $questionsData = $sectionData['questions'] ?? $sectionData['exam_questions'] ?? [];
            
            // Remove non-column fields
            unset($sectionData['questions']);
            unset($sectionData['exam_questions']);
            unset($sectionData['context']);
            unset($sectionData['media_type']);

            $section = $exam->sections()->updateOrCreate(
                ['id' => $sectionData['id'] ?? null],
                $sectionData
            );

            $sectionIds[] = $section->id;

            // Sync Questions for this section
            $pivotData = [];
            foreach ($questionsData as $qIndex => $questionData) {
                if (isset($questionData['id'])) {
                    $questionId = $questionData['id'];

                    // Check if question already added to this exam (in any section)
                    if (in_array($questionId, $allQuestionIds)) {
                        continue; // Skip duplicates
                    }

                    // Verify question belongs to the same course
                    $question = \App\Models\Question::find($questionId);
                    if (!$question || $question->course_id !== $exam->course_id) {
                        continue; // Skip questions from other courses
                    }

                    $pivotData[$questionId] = [
                        'order' => $questionData['order'] ?? ($qIndex + 1),
                        'points' => $questionData['points'] ?? null,
                    ];
                    $allQuestionIds[] = $questionId;
                }
            }

            // Sync pivot table
            $section->questions()->sync($pivotData);
        }

        // Remove sections not in the request
        $exam->sections()->whereNotIn('id', $sectionIds)->delete();
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(Course $course, Exam $exam, DestroyExamRequest $request): JsonResponse
    {
        // Check if there are any attempts for this exam
        if ($exam->attempts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete exam as it has been attempted by users'
            ], 422);
        }

        // Delete associated sections (cascades to pivot tables)
        $exam->sections()->delete();

        $exam->delete();

        return response()->json([
            'message' => 'Exam deleted successfully'
        ]);
    }
}
