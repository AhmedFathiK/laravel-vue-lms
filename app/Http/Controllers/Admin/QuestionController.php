<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\DestroyQuestionRequest;
use App\Http\Requests\Admin\Question\IndexQuestionRequest;
use App\Http\Requests\Admin\Question\ShowQuestionRequest;
use App\Http\Requests\Admin\Question\StoreQuestionRequest;
use App\Http\Requests\Admin\Question\UpdateQuestionRequest;
use App\Models\Question;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    /**
     * Display a listing of the questions.
     */
    public function index(IndexQuestionRequest $request): JsonResponse
    {
        $query = Question::query();

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

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->has('tags')) {
            $tags = explode(',', $request->tags);
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', trim($tag));
                }
            });
        }

        // Order by
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_direction', 'desc'));

        // Paginate
        $perPage = $request->get('per_page', 15);
        $questions = $query->paginate($perPage);

        return response()->json($questions);
    }

    /**
     * Store a newly created question in storage.
     */
    public function store(StoreQuestionRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Process question data based on type
        $this->processQuestionDataByType($data);

        $question = Question::create($data);

        return response()->json([
            'message' => 'Question created successfully',
            'question' => $question
        ], 201);
    }

    /**
     * Display the specified question.
     */
    public function show(Question $question, ShowQuestionRequest $request): JsonResponse
    {
        return response()->json($question);
    }

    /**
     * Update the specified question in storage.
     */
    public function update(UpdateQuestionRequest $request, Question $question): JsonResponse
    {
        $data = $request->validated();

        // Process question data based on type
        $this->processQuestionDataByType($data);

        $question->update($data);

        return response()->json([
            'message' => 'Question updated successfully',
            'question' => $question
        ]);
    }

    /**
     * Remove the specified question from storage.
     */
    public function destroy(Question $question, DestroyQuestionRequest $request): JsonResponse
    {
        // Check if the question is used in any exams
        if ($question->examSections()->exists()) {
            return response()->json([
                'message' => 'Cannot delete question as it is used in one or more exams'
            ], 422);
        }

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully'
        ]);
    }

    /**
     * Process question data based on its type to ensure correct format in the database
     */
    private function processQuestionDataByType(array &$data): void
    {
        $type = $data['type'] ?? null;

        if (!$type) {
            return;
        }

        // Ensure options and correct_answer are arrays
        if (!isset($data['options'])) {
            $data['options'] = [];
        }

        if (!isset($data['correct_answer'])) {
            $data['correct_answer'] = [];
        }

        switch ($type) {
            case Question::TYPE_MCQ:
                // For MCQ, options is an array of strings and correct_answer is an array of indices
                break;

            case Question::TYPE_FILL_BLANK:
                // For fill in the blank, correct_answer is an array of possible correct answers
                // No options needed
                $data['options'] = [];
                break;

            case Question::TYPE_FILL_BLANK_CHOICES:
                // For fill in the blank with choices, store the blanks structure in options
                // and correct answers in correct_answer
                if (isset($data['blanks']) && is_array($data['blanks'])) {
                    $data['options'] = $data['blanks'];
                    $data['correct_answer'] = array_map(function ($blank) {
                        return [
                            'id' => $blank['id'] ?? uniqid(),
                            'answer' => $blank['correct_answer'] ?? null
                        ];
                    }, $data['blanks']);

                    // Remove blanks from data as it's not in the database schema
                    unset($data['blanks']);
                }
                break;

            case Question::TYPE_MATCHING:
                // For matching, store pairs in options and correct mappings in correct_answer
                if (isset($data['matching_pairs']) && is_array($data['matching_pairs'])) {
                    $data['options'] = $data['matching_pairs'];

                    // Create mapping for correct answers
                    $data['correct_answer'] = array_map(function ($index) {
                        return [
                            'left' => $index,
                            'right' => $index
                        ];
                    }, array_keys($data['matching_pairs']));

                    // Remove matching_pairs from data
                    unset($data['matching_pairs']);
                }
                break;

            case Question::TYPE_REORDERING:
                // For reordering, store items in options and correct order in correct_answer
                if (isset($data['reordering_items']) && is_array($data['reordering_items'])) {
                    $data['options'] = $data['reordering_items'];
                    $data['correct_answer'] = array_keys($data['reordering_items']);

                    // Remove reordering_items from data
                    unset($data['reordering_items']);
                }
                break;

            case Question::TYPE_WRITING:
                // For writing, store grading guidelines and word limits in options
                $data['options'] = [
                    'grading_guidelines' => $data['grading_guidelines'] ?? '',
                    'min_words' => $data['min_words'] ?? 0,
                    'max_words' => $data['max_words'] ?? 0,
                ];

                // No correct answers for writing questions
                $data['correct_answer'] = [];

                // Remove extra fields from data
                unset($data['grading_guidelines'], $data['min_words'], $data['max_words']);
                break;
        }
    }
}
