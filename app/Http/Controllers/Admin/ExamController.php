<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exam\DestroyExamRequest;
use App\Http\Requests\Admin\Exam\IndexExamRequest;
use App\Http\Requests\Admin\Exam\ShowExamRequest;
use App\Http\Requests\Admin\Exam\StoreExamRequest;
use App\Http\Requests\Admin\Exam\UpdateExamRequest;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;

class ExamController extends Controller
{
    /**
     * Display a listing of the exams.
     */
    public function index(IndexExamRequest $request): JsonResponse
    {
        $query = Exam::query();

        // Apply filters
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

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
    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = Exam::create($request->validated());

        return response()->json([
            'message' => 'Exam created successfully',
            'exam' => $exam
        ], 201);
    }

    /**
     * Display the specified exam.
     */
    public function show(Exam $exam, ShowExamRequest $request): JsonResponse
    {
        $exam->load('sections.questions');

        return response()->json($exam);
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $exam->update($request->validated());

        return response()->json([
            'message' => 'Exam updated successfully',
            'exam' => $exam
        ]);
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(Exam $exam, DestroyExamRequest $request): JsonResponse
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
