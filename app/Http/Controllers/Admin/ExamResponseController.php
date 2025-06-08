<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExamResponse\GradeWritingResponseRequest;
use App\Models\ExamResponse;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use App\Notifications\WritingResponseGraded;

class ExamResponseController extends Controller
{
    /**
     * Display a listing of the pending writing responses.
     */
    public function pendingResponses(Request $request): JsonResponse
    {
        if (!Gate::allows('grade exams')) {
            abort(403);
        }

        $query = ExamResponse::where('status', ExamResponse::STATUS_PENDING_REVIEW)
            ->whereHas('question', function ($q) {
                $q->where('type', Question::TYPE_WRITING);
            })
            ->with([
                'examAttempt.user',
                'examAttempt.exam',
                'question'
            ]);

        // Apply filters
        if ($request->has('course_id')) {
            $query->whereHas('examAttempt.exam', function ($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        if ($request->has('exam_id')) {
            $query->whereHas('examAttempt', function ($q) use ($request) {
                $q->where('exam_id', $request->exam_id);
            });
        }

        if ($request->has('user_id')) {
            $query->whereHas('examAttempt', function ($q) use ($request) {
                $q->where('user_id', $request->user_id);
            });
        }

        // Order by (newest first by default)
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_direction', 'desc'));

        // Paginate
        $perPage = $request->get('per_page', 15);
        $responses = $query->paginate($perPage);

        return response()->json($responses);
    }

    /**
     * Grade a writing response.
     */
    public function gradeResponse(GradeWritingResponseRequest $request, ExamResponse $response): JsonResponse
    {
        if (!Gate::allows('grade exams')) {
            abort(403);
        }

        // Check if this is a writing question and it's pending review
        if ($response->question->type !== Question::TYPE_WRITING || $response->status !== ExamResponse::STATUS_PENDING_REVIEW) {
            return response()->json([
                'message' => 'Only pending writing responses can be graded'
            ], 422);
        }

        // Update the response with the grade
        $response->update([
            'score' => $request->score,
            'feedback' => $request->feedback,
            'status' => ExamResponse::STATUS_GRADED,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);

        // Recalculate the exam attempt score
        $response->examAttempt->calculateScore();

        // Notify the learner that their response has been graded
        $user = User::find($response->examAttempt->user_id);
        if ($user) {
            Notification::send($user, new WritingResponseGraded($response));
        }

        return response()->json([
            'message' => 'Response graded successfully',
            'response' => $response,
            'exam_attempt' => $response->examAttempt
        ]);
    }

    /**
     * Show details of a specific response.
     */
    public function show(ExamResponse $response): JsonResponse
    {
        if (!Gate::allows('grade exams')) {
            abort(403);
        }

        $response->load([
            'examAttempt.user',
            'examAttempt.exam',
            'question',
            'gradedBy'
        ]);

        return response()->json($response);
    }
}
