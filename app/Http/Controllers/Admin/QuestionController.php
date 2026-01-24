<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\DestroyQuestionRequest;
use App\Http\Requests\Admin\Question\IndexQuestionRequest;
use App\Http\Requests\Admin\Question\ShowQuestionRequest;
use App\Http\Requests\Admin\Question\StoreQuestionRequest;
use App\Http\Requests\Admin\Question\UpdateQuestionRequest;
use App\Http\Resources\Admin\QuestionResource;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Request;

class QuestionController extends Controller
{
    /**
     * Display a listing of the questions.
     */
    public function index(IndexQuestionRequest $request, Course $course): JsonResponse
    {
        $query = Question::query()->with(['terms', 'concepts']);

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

        if ($request->boolean('no_context')) {
            $query->whereNull('question_context_id');
        }

        if ($request->has('exclude_ids')) {
            $excludeIds = is_array($request->exclude_ids) ? $request->exclude_ids : explode(',', $request->exclude_ids);
            $query->whereNotIn('id', $excludeIds);
        }

        // Order by
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_direction', 'desc'));

        // Paginate
        $perPage = $request->get('per_page', 15);
        $questions = $query->paginate($perPage);

        return response()->json([
            'data' => QuestionResource::collection($questions->items()),
            'total' => $questions->total(),
            'currentPage' => $questions->currentPage(),
            'perPage' => $questions->perPage(),
            'lastPage' => $questions->lastPage(),
        ]);
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

        return response()->json(QuestionResource::collection($questions));
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

        // Handle audio file upload if present
        if ($request->hasFile('audio_file')) {
            $data['audio_url'] = $this->handleMediaUpload($request->file('audio_file'), 'image_with_audio');
        }

        // Process question data based on type
        $this->processQuestionDataByType($data);

        // Create question instance
        $content = null;
        if (isset($data['content'])) {
            $content = $data['content'];
            unset($data['content']);
        }

        $question = new Question($data);
        $question->course_id = $course->id;

        if ($content) {
            $question->setTranslation('content', app()->getLocale(), $content);
        }

        $question->save();

        if (isset($data['term_ids'])) {
            $question->terms()->sync($data['term_ids']);
        }

        if (isset($data['concept_ids'])) {
            $question->concepts()->sync($data['concept_ids']);
        }

        $question->load(['terms', 'concepts']);

        return response()->json([
            'message' => 'Question created successfully',
            'question' => new QuestionResource($question)
        ], 201);
    }

    /**
     * Display the specified question.
     */
    public function show(Course $course, Question $question, ShowQuestionRequest $request): JsonResponse
    {
        $question->load(['terms', 'concepts']);

        return response()->json(new QuestionResource($question));
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
            if ($question->media_url && $question->media_type !== 'none' && strpos($question->media_url, '/storage/') === 0) {
                $this->deleteOldMedia($question->media_url);
            }

            $data['media_url'] = $this->handleMediaUpload($request->file('media'), $data['media_type']);
        }

        // Handle audio file upload if present
        if ($request->hasFile('audio_file')) {
            // Delete old audio file if exists
            if ($question->audio_url && strpos($question->audio_url, '/storage/') === 0) {
                $this->deleteOldMedia($question->audio_url);
            }
            $data['audio_url'] = $this->handleMediaUpload($request->file('audio_file'), 'image_with_audio');
        }

        // If media type is changed to none, remove media_url and audio_url
        if (isset($data['media_type']) && $data['media_type'] === 'none') {
            // Delete old media file if exists
            if ($question->media_url && $question->media_type !== 'none' && strpos($question->media_url, '/storage/') === 0) {
                $this->deleteOldMedia($question->media_url);
            }
            // Delete old audio file if exists
            if ($question->audio_url && strpos($question->audio_url, '/storage/') === 0) {
                $this->deleteOldMedia($question->audio_url);
            }
            $data['media_url'] = null;
            $data['audio_url'] = null;
            $data['video_source'] = null;
        }

        // If not video, clear video_source
        if (isset($data['media_type']) && $data['media_type'] !== 'video') {
            $data['video_source'] = null;
        }

        // Process question data based on type
        $this->processQuestionDataByType($data);

        if (isset($data['content'])) {
            $question->setTranslation('content', app()->getLocale(), $data['content']);
            unset($data['content']);
        }

        $question->fill($data);
        $question->save();

        if (isset($data['term_ids'])) {
            $question->terms()->sync($data['term_ids']);
        }

        if (isset($data['concept_ids'])) {
            $question->concepts()->sync($data['concept_ids']);
        }

        $question->load(['terms', 'concepts']);

        return response()->json([
            'message' => 'Question updated successfully',
            'question' => new QuestionResource($question)
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
        // Remove media fields as they are not in the database schema
        if (isset($data['media'])) {
            unset($data['media']);
        }
        if (isset($data['audio_file'])) {
            unset($data['audio_file']);
        }

        $type = $data['type'] ?? null;

        if (!$type) {
            return;
        }

        $content = [];

        switch ($type) {
            case Question::TYPE_MCQ:
                // For MCQ, content includes options and correct_answer
                $content = [
                    'options' => $data['options'] ?? [],
                    'correctAnswer' => $data['correct_answer'] ?? [],
                ];
                break;

            case Question::TYPE_FILL_BLANK:
                // For fill in the blank, correct_answer is an array of possible correct answers
                $content = [
                    'correctAnswer' => $data['correct_answer'] ?? [],
                ];
                break;

            case Question::TYPE_FILL_BLANK_CHOICES:
                // For fill in the blank with choices, store the blanks structure
                if (isset($data['blanks']) && is_array($data['blanks'])) {
                    $content['blanks'] = array_map(function ($blank) {
                        if (isset($blank['correct_answer'])) {
                            $blank['correctAnswer'] = $blank['correct_answer'];
                            unset($blank['correct_answer']);
                        }
                        return $blank;
                    }, $data['blanks']);
                }
                break;

            case Question::TYPE_MATCHING:
                // For matching, store pairs
                if (isset($data['matching_pairs']) && is_array($data['matching_pairs'])) {
                    $content['pairs'] = $data['matching_pairs'];
                }
                break;

            case Question::TYPE_REORDERING:
                // For reordering, store items
                if (isset($data['reordering_items']) && is_array($data['reordering_items'])) {
                    $content['items'] = $data['reordering_items'];
                }
                break;

            case Question::TYPE_WRITING:
                // For writing, store grading guidelines and limits
                $content = [
                    'grading_guidelines' => $data['grading_guidelines'] ?? '',
                    'min_words' => $data['min_words'] ?? 0,
                    'max_words' => $data['max_words'] ?? 0,
                ];
                break;
        }

        // Assign to content field
        $data['content'] = $content;

        // Clean up legacy fields from input data
        unset(
            $data['options'],
            $data['correct_answer'],
            $data['blanks'],
            $data['matching_pairs'],
            $data['reordering_items'],
            $data['grading_guidelines'],
            $data['min_words'],
            $data['max_words']
        );
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
