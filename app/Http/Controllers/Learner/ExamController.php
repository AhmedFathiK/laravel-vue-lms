<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamResponse;
use App\Models\ExamSection;
use App\Models\Question;
use App\Services\FeatureAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    protected FeatureAccessService $featureAccessService;

    public function __construct(FeatureAccessService $featureAccessService)
    {
        $this->featureAccessService = $featureAccessService;
    }

    /**
     * Get available exams for a course, level, or lesson.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Exam::where('is_active', true)
            ->where('status', 'published');

        // Filter by course, level, or lesson
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

        $exams = $query->get();

        // Add attempt information for the current user
        $exams->each(function ($exam) {
            $attempts = ExamAttempt::where('user_id', Auth::id())
                ->where('exam_id', $exam->id)
                ->get();

            $exam->attempts_count = $attempts->count();
            $exam->best_score = $attempts->max('percentage');
            $exam->is_passed = $attempts->contains('is_passed', true);
            $exam->has_pending_writing = $attempts->contains(function ($attempt) {
                return $attempt->status === ExamAttempt::STATUS_PENDING_REVIEW;
            });
        });

        return response()->json($exams);
    }

    /**
     * Get a specific exam with sections and questions.
     */
    public function show(Exam $exam): JsonResponse
    {
        // Check if the exam is active and published
        if (!$exam->is_active || $exam->status !== 'published') {
            return response()->json([
                'message' => 'Exam not available'
            ], 404);
        }

        // Check placement test feature
        $course = $exam->course;
        if ($course && $course->placement_exam_id === $exam->id) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$this->featureAccessService->hasFeatureForCourse($user, 'placement_test.access', $course)) {
                return response()->json([
                    'message' => 'You do not have access to the placement test.'
                ], 403);
            }
        }

        // Check if the user has reached the maximum attempts
        if ($exam->max_attempts > 0) {
            $attemptsCount = ExamAttempt::where('user_id', Auth::id())
                ->where('exam_id', $exam->id)
                ->count();

            if ($attemptsCount >= $exam->max_attempts) {
                return response()->json([
                    'message' => 'Maximum attempts reached for this exam'
                ], 403);
            }
        }

        // Load sections and questions (exclude correct answers for security)
        $exam->load(['sections' => function ($query) use ($exam) {
            $query->orderBy('order');

            $query->with(['questions' => function ($q) use ($exam) {
                $q->orderByPivot('order');
                $q->select(
                    'questions.id',
                    'questions.question_context_id',
                    'questions.title',
                    'questions.question_text',
                    'questions.type',
                    'questions.content',
                    'questions.media_url',
                    'questions.media_type',
                    'questions.audio_url',
                    'questions.video_source'
                );
                // Include pivot fields
                $q->withPivot('order', 'points');
                $q->with('context');

                if ($exam->randomize_questions) {
                    $q->inRandomOrder();
                }
            }]);
        }]);

        // Transform questions to include pivot points as marks and map options from content
        $exam->duration = $exam->time_limit; // Map for frontend

        $exam->sections->each(function ($section) {
            $section->questions->each(function ($question) {
                $question->marks = $question->pivot->points ?? $question->points; // Use pivot points or default
                $question->order = $question->pivot->order;

                // Map options from content if they exist
                if (isset($question->content['options'])) {
                    $question->options = $question->content['options'];
                }
            });
        });

        return response()->json($exam);
    }

    /**
     * Start a new exam attempt.
     */
    public function startAttempt(Exam $exam): JsonResponse
    {
        // Check if the exam is active and published
        if (!$exam->is_active || $exam->status !== 'published') {
            return response()->json([
                'message' => 'Exam not available'
            ], 404);
        }

        // Check if user has reached the maximum attempts
        if ($exam->max_attempts > 0) {
            $attemptsCount = ExamAttempt::where('user_id', Auth::id())
                ->where('exam_id', $exam->id)
                ->count();

            if ($attemptsCount >= $exam->max_attempts) {
                return response()->json([
                    'message' => 'Maximum attempts reached for this exam'
                ], 403);
            }
        }

        // Check if there's an in-progress attempt
        $inProgressAttempt = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->first();

        if ($inProgressAttempt) {
            return response()->json([
                'message' => 'You already have an attempt in progress',
                'attempt' => $inProgressAttempt
            ]);
        }

        // Create a new attempt
        $attemptNumber = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->count() + 1;

        $attempt = ExamAttempt::create([
            'user_id' => Auth::id(),
            'exam_id' => $exam->id,
            'start_time' => now(),
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
            'attempt_number' => $attemptNumber,
        ]);

        // Add remaining time for frontend
        $attempt->remaining_time = $attempt->getRemainingTime();

        return response()->json([
            'message' => 'Exam attempt started successfully',
            'attempt' => $attempt
        ], 201);
    }

    /**
     * Submit a response to a question in an exam.
     */
    public function submitResponse(Request $request, $attempt_id, $question_id): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attempt_id);
        $question = Question::findOrFail($question_id);

        // Check if the attempt belongs to the authenticated user
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if the attempt is expired (backend time limit enforcement)
        if ($attempt->isExpired()) {
            // Auto-complete the attempt
            $this->completeAttempt($attempt);

            return response()->json([
                'message' => 'Time limit exceeded. Your exam has been automatically submitted.',
                'is_expired' => true
            ], 403);
        }

        // Check if the attempt is still in progress
        if ($attempt->status !== ExamAttempt::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Exam attempt is already completed'
            ], 422);
        }

        // Validate the request
        $request->validate([
            'user_answer' => 'required',
            'section_id' => 'required|exists:exam_sections,id',
        ]);

        // Check if the question belongs to the exam
        $section = ExamSection::find($request->section_id);
        if (!$section || $section->exam_id !== $attempt->exam_id) {
            return response()->json([
                'message' => 'Invalid section'
            ], 422);
        }

        // Check if the question is in the section
        $questionExists = $section->questions()->where('questions.id', $question->id)->exists();
        if (!$questionExists) {
            return response()->json([
                'message' => 'Question not found in this section'
            ], 422);
        }

        // Check if there's already a response for this question in this attempt
        $existingResponse = ExamResponse::where('exam_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        if ($existingResponse) {
            // Update the existing response
            $existingResponse->update([
                'user_answer' => $request->user_answer,
            ]);

            // Re-grade the response
            $existingResponse->autoGrade();

            return response()->json([
                'message' => 'Response updated successfully',
                'response' => $existingResponse
            ]);
        }

        // Create a new response
        $response = ExamResponse::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'user_answer' => $request->user_answer,
        ]);

        // Auto-grade the response
        $response->autoGrade();

        return response()->json([
            'message' => 'Response submitted successfully',
            'response' => $response
        ], 201);
    }



    /**
     * Complete an exam attempt.
     */
    public function completeAttempt(ExamAttempt $attempt): JsonResponse
    {
        // Check if the attempt belongs to the authenticated user
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if the attempt is still in progress
        if ($attempt->status !== ExamAttempt::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Exam attempt is already completed'
            ], 422);
        }

        // Set the end time and status
        $attempt->end_time = now();
        $attempt->time_spent = $attempt->end_time->diffInSeconds($attempt->start_time);
        $attempt->status = ExamAttempt::STATUS_COMPLETED;
        $attempt->save();

        // Calculate the score
        $attempt->calculateScore();

        // Load the attempt with responses and placement level
        $attempt->load(['responses.question', 'placementOutcomeLevel']);

        // If show_answers is enabled for the exam, include the correct answers
        if ($attempt->exam->show_answers) {
            $attempt->setRelation('responses', $attempt->responses->each(function ($response) {
                $question = $response->question;
                $response->correct_answer = $question ? $question->correct_answer : null;
            }));
        }

        return response()->json([
            'message' => 'Exam attempt completed successfully',
            'attempt' => $attempt,
            'has_pending_writing' => $attempt->status === ExamAttempt::STATUS_PENDING_REVIEW,
        ]);
    }

    /**
     * Get all attempts for a specific exam.
     */
    public function examAttempts(Exam $exam): JsonResponse
    {
        $attempts = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($attempts);
    }

    /**
     * Get a specific attempt with responses.
     */
    public function showAttempt(ExamAttempt $attempt): JsonResponse
    {
        // Check if the attempt belongs to the authenticated user
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Load the attempt with responses and questions
        $attempt->load(['responses.question', 'exam']);

        // Add remaining time for frontend
        $attempt->remaining_time = $attempt->getRemainingTime();

        // If show_answers is enabled for the exam, include the correct answers
        if ($attempt->exam->show_answers && in_array($attempt->status, [
            ExamAttempt::STATUS_COMPLETED,
            ExamAttempt::STATUS_GRADED
        ])) {
            $attempt->setRelation('responses', $attempt->responses->each(function ($response) {
                $question = $response->question;
                $response->correct_answer = $question ? $question->correct_answer : null;
            }));
        }

        return response()->json($attempt);
    }

    /**
     * Get the placement test for a course.
     */
    public function getPlacementTest(Request $request): JsonResponse
    {
        $courseId = $request->input('course_id');
        $course = \App\Models\Course::with('placementExam')->find($courseId);

        if (!$course || !$course->placementExam) {
            return response()->json([
                'message' => 'No placement test available for this course'
            ], 404);
        }

        $placementTest = $course->placementExam;

        if (!$placementTest->is_active || $placementTest->status !== 'published') {
            return response()->json([
                'message' => 'Placement test is not currently available'
            ], 404);
        }

        return response()->json($placementTest);
    }
}
