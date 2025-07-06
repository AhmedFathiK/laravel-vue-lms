<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\DestroyQuestionRequest;
use App\Http\Requests\Admin\Question\IndexQuestionRequest;
use App\Http\Requests\Admin\Question\ShowQuestionRequest;
use App\Http\Requests\Admin\Question\StoreQuestionRequest;
use App\Http\Requests\Admin\Question\UpdateQuestionRequest;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class QuestionController extends Controller
{
    /**
     * Display a listing of the questions.
     */
    public function index(IndexQuestionRequest $request, Course $course): JsonResponse
    {
        $query = Question::query();

        // Apply filters
        $query->where('course_id', $course->id);

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

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('question_text', 'LIKE', "%{$search}%");
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
     * Display a listing of the questions.
     */
    public function getQuestionsForSelectFields(IndexQuestionRequest $request, Course $course): JsonResponse
    {
        $query = Question::query();

        // Apply filters
        $query->where('course_id', $course->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('question_text', 'LIKE', "%{$search}%");
            });
        }

        $questions = $query->get();

        return response()->json($questions);
    }

    /**
     * Store a newly created question in storage.
     */
    public function store(StoreQuestionRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();

        // Handle media file upload if present
        if ($request->hasFile('media')) {
            if (!isset($data['media_type'])) {
                $data['media_type'] = 'image'; // Default to image if not specified
            }
            $data['media_url'] = $this->handleMediaUpload($request->file('media'), $data['media_type']);
        }

        // For video type, media_url is directly provided as a URL
        if (isset($data['media_type']) && $data['media_type'] === 'video' && isset($data['media_url'])) {
            // No processing needed as media_url is already set from the request
        }

        // For image_with_audio type, audio_url is directly provided
        if (isset($data['media_type']) && $data['media_type'] === 'image_with_audio' && isset($data['audio_url'])) {
            // No processing needed as audio_url is already set from the request
        }

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
    public function show(Question $question, Course $course, ShowQuestionRequest $request): JsonResponse
    {
        return response()->json($question);
    }

    /**
     * Update the specified question in storage.
     */
    public function update(UpdateQuestionRequest $request, Course $course, Question $question): JsonResponse
    {
        $data = $request->validated();

        // Handle media file upload if present and media type is there
        if ($request->hasFile('media') && isset($data['media_type'])) {
            // Delete old media file if exists and different from default
            if ($question->media_url && $question->media_type !== 'none') {
                $this->deleteOldMedia($question->media_url);
            }

            $data['media_url'] = $this->handleMediaUpload($request->file('media'), $data['media_type']);
        }

        // For video type, media_url is directly provided as a URL
        if (isset($data['media_type']) && $data['media_type'] === 'video' && isset($data['media_url'])) {
            // If changing from a file-based video to a URL-based video, delete the old file
            if ($question->media_url && $question->media_type === 'video' && strpos($question->media_url, '/storage/') === 0) {
                $this->deleteOldMedia($question->media_url);
            }
            // No processing needed as media_url is already set from the request
        }

        // For image_with_audio type, audio_url is directly provided
        if (isset($data['media_type']) && $data['media_type'] === 'image_with_audio' && isset($data['audio_url'])) {
            // No processing needed as audio_url is already set from the request
        } else if (isset($data['media_type']) && $data['media_type'] !== 'image_with_audio') {
            // If changing from image_with_audio to another type, clear the audio_url
            $data['audio_url'] = null;
        }

        // If media type is changed to none, remove media_url and audio_url
        if (isset($data['media_type']) && $data['media_type'] === 'none') {
            // Delete old media file if exists
            if ($question->media_url && $question->media_type !== 'none') {
                $this->deleteOldMedia($question->media_url);
            }
            $data['media_url'] = null;
            $data['audio_url'] = null;
        }

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
    public function destroy(DestroyQuestionRequest $request, Course $course, Question $question): JsonResponse
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
        // Remove media field as it's not in the database schema
        if (isset($data['media'])) {
            unset($data['media']);
        }

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

    /**
     * Handle media file upload and return the file path
     */
    private function handleMediaUpload($file, string $mediaType): string
    {
        // Determine the storage directory based on media type
        $directory = 'questions/';

        switch ($mediaType) {
            case 'image':
            case 'image_with_audio':
                $directory .= 'images';
                break;
            case 'video':
                $directory .= 'videos';
                break;
            default:
                $directory .= 'media';
        }

        // Store the file
        $path = $file->store($directory, 'public');

        // Return the URL to access the file
        return Storage::url($path);
    }

    /**
     * Delete old media file
     */
    private function deleteOldMedia(string $mediaUrl): void
    {
        // Convert storage URL to path
        $path = str_replace('/storage', '', $mediaUrl);

        // Delete the file if it exists
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
