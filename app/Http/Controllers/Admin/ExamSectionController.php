<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamSection\StoreExamSectionRequest;
use App\Http\Requests\Admin\ExamSection\UpdateExamSectionRequest;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExamSectionController extends Controller
{
    /**
     * Display a listing of the exam sections.
     */
    public function index(Request $request, Exam $exam): JsonResponse
    {
        if (!Gate::allows('view exam sections')) {
            abort(403);
        }

        $sections = $exam->sections()->with('questions')->orderBy('order')->get();

        return response()->json($sections);
    }

    /**
     * Store a newly created exam section in storage.
     */
    public function store(StoreExamSectionRequest $request): JsonResponse
    {
        // Create the section
        $section = ExamSection::create($request->validated());

        // Attach questions if provided
        if ($request->has('questions') && is_array($request->questions)) {
            $this->attachQuestions($section, $request->questions);
        }

        return response()->json([
            'message' => 'Exam section created successfully',
            'section' => $section->load('questions')
        ], 201);
    }

    /**
     * Display the specified exam section.
     */
    public function show(ExamSection $section): JsonResponse
    {
        if (!Gate::allows('view exam sections')) {
            abort(403);
        }

        $section->load('questions');

        return response()->json($section);
    }

    /**
     * Update the specified exam section in storage.
     */
    public function update(UpdateExamSectionRequest $request, ExamSection $section): JsonResponse
    {
        $section->update($request->validated());

        // Update questions if provided
        if ($request->has('questions') && is_array($request->questions)) {
            // Detach all existing questions
            $section->questions()->detach();

            // Attach new questions
            $this->attachQuestions($section, $request->questions);
        }

        return response()->json([
            'message' => 'Exam section updated successfully',
            'section' => $section->fresh()->load('questions')
        ]);
    }

    /**
     * Remove the specified exam section from storage.
     */
    public function destroy(ExamSection $section): JsonResponse
    {
        if (!Gate::allows('delete exam sections')) {
            abort(403);
        }

        // Detach all questions first
        $section->questions()->detach();

        $section->delete();

        return response()->json([
            'message' => 'Exam section deleted successfully'
        ]);
    }

    /**
     * Add a question to an exam section.
     */
    public function addQuestion(Request $request, ExamSection $section): JsonResponse
    {
        if (!Gate::allows('edit exam sections')) {
            abort(403);
        }

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'order' => 'required|integer|min:0',
        ]);

        // Check if the question is already in the section
        if ($section->questions()->where('question_id', $request->question_id)->exists()) {
            return response()->json([
                'message' => 'Question already exists in this section'
            ], 422);
        }

        $section->questions()->attach($request->question_id, [
            'order' => $request->order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Question added to section successfully'
        ]);
    }

    /**
     * Remove a question from an exam section.
     */
    public function removeQuestion(Request $request, ExamSection $section, Question $question): JsonResponse
    {
        if (!Gate::allows('edit exam sections')) {
            abort(403);
        }

        $section->questions()->detach($question->id);

        return response()->json([
            'message' => 'Question removed from section successfully'
        ]);
    }

    /**
     * Reorder questions in an exam section.
     */
    public function reorderQuestions(Request $request, ExamSection $section): JsonResponse
    {
        if (!Gate::allows('edit exam sections')) {
            abort(403);
        }

        $request->validate([
            'questions' => 'required|array',
            'questions.*.id' => 'required|exists:questions,id',
            'questions.*.order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request, $section) {
            foreach ($request->questions as $questionData) {
                $section->questions()->updateExistingPivot(
                    $questionData['id'],
                    ['order' => $questionData['order']]
                );
            }
        });

        return response()->json([
            'message' => 'Questions reordered successfully'
        ]);
    }

    /**
     * Helper method to attach questions to a section.
     */
    private function attachQuestions(ExamSection $section, array $questions): void
    {
        $questionData = [];

        foreach ($questions as $question) {
            if (isset($question['id']) && isset($question['order'])) {
                $questionData[$question['id']] = [
                    'order' => $question['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (count($questionData) > 0) {
            $section->questions()->attach($questionData);
        }
    }
}
