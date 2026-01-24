<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuestionContext;
use App\Http\Resources\Admin\QuestionContextResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionContextController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $query = $course->questionContexts()->with(['questions']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $contexts = $query->latest()->paginate($request->get('per_page', 15));

        return QuestionContextResource::collection($contexts);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, QuestionContext $questionContext): QuestionContextResource
    {
        if ($questionContext->course_id !== $course->id) {
            abort(404);
        }

        $questionContext->load(['questions']);
        return new QuestionContextResource($questionContext);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course): QuestionContextResource
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'context_type' => 'required|string|in:text_passage,image,video,audio,image_with_audio',
            'content' => 'required_if:context_type,text_passage|nullable|string',
            'media_url' => 'nullable|string',
            'audio_url' => 'nullable|string',
            'video_source' => 'nullable|string|in:direct,youtube,vimeo',
            'media_file' => 'nullable|file|max:10240', // 10MB max
            'audio_file' => 'nullable|file|max:10240', // 10MB max
        ]);

        // Handle File Uploads - prioritize file over URL
        if ($request->hasFile('media_file')) {
            $path = $request->file('media_file')->store('course-assets', 'public');
            $validated['media_url'] = '/storage/' . $path;
        }

        if ($request->hasFile('audio_file')) {
            $path = $request->file('audio_file')->store('course-assets', 'public');
            $validated['audio_url'] = '/storage/' . $path;
        }

        // Clean up data based on type
        if ($validated['context_type'] === 'text_passage') {
            $validated['media_url'] = null;
            $validated['audio_url'] = null;
            $validated['video_source'] = null;
        } elseif ($validated['context_type'] === 'image') {
            $validated['content'] = null;
            $validated['audio_url'] = null;
            $validated['video_source'] = null;
        } elseif ($validated['context_type'] === 'video') {
            $validated['content'] = null;
            $validated['audio_url'] = null;
            // Ensure video source is set correctly based on input
            if ($request->hasFile('media_file')) {
                $validated['video_source'] = 'direct';
            } elseif (empty($validated['video_source']) && !empty($validated['media_url'])) {
                // Try to guess if not provided but URL is there (fallback)
                if (strpos($validated['media_url'], 'youtube') !== false || strpos($validated['media_url'], 'youtu.be') !== false) {
                    $validated['video_source'] = 'youtube';
                } elseif (strpos($validated['media_url'], 'vimeo') !== false) {
                    $validated['video_source'] = 'vimeo';
                } else {
                    $validated['video_source'] = 'direct';
                }
            }
        } elseif ($validated['context_type'] === 'audio') {
            $validated['content'] = null;
            $validated['video_source'] = null;
            // For audio, we use media_url to avoid redundancy
            if ($request->hasFile('media_file')) {
                $path = $request->file('media_file')->store('course-assets', 'public');
                $validated['media_url'] = '/storage/' . $path;
            }
            $validated['audio_url'] = null;
        } elseif ($validated['context_type'] === 'image_with_audio') {
            $validated['content'] = null;
            $validated['video_source'] = null;
        }

        $questionContext = $course->questionContexts()->create($validated);

        return new QuestionContextResource($questionContext);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course, QuestionContext $questionContext): QuestionContextResource
    {
        if ($questionContext->course_id !== $course->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'context_type' => 'required|string|in:text_passage,image,video,audio,image_with_audio',
            'content' => 'required_if:context_type,text_passage|nullable|string',
            'media_url' => 'nullable|string',
            'audio_url' => 'nullable|string',
            'video_source' => 'nullable|string|in:direct,youtube,vimeo',
            'media_file' => 'nullable|file|max:10240',
            'audio_file' => 'nullable|file|max:10240',
        ]);

        // Handle File Uploads and Delete Old Files - prioritize file over URL
        if ($request->hasFile('media_file')) {
            if ($questionContext->media_url && str_starts_with($questionContext->media_url, '/storage/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $questionContext->media_url));
            }
            $path = $request->file('media_file')->store('course-assets', 'public');
            $validated['media_url'] = '/storage/' . $path;
            if ($validated['context_type'] === 'video') {
                $validated['video_source'] = 'direct';
            }
        }

        if ($request->hasFile('audio_file')) {
            if ($questionContext->audio_url && str_starts_with($questionContext->audio_url, '/storage/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $questionContext->audio_url));
            }
            $path = $request->file('audio_file')->store('course-assets', 'public');
            $validated['audio_url'] = '/storage/' . $path;
        }

        // Clean up data based on type and delete old files if type changed
        if ($validated['context_type'] === 'text_passage') {
            $this->deleteFiles($questionContext);
            $validated['media_url'] = null;
            $validated['audio_url'] = null;
            $validated['video_source'] = null;
        } elseif ($validated['context_type'] === 'image') {
            $this->deleteAudio($questionContext);
            $validated['content'] = null;
            $validated['audio_url'] = null;
            $validated['video_source'] = null;
        } elseif ($validated['context_type'] === 'video') {
            $this->deleteAudio($questionContext);
            $validated['content'] = null;
            $validated['audio_url'] = null;
            // Ensure video source is updated if not direct (file upload handled above)
            if (!$request->hasFile('media_file') && empty($validated['video_source']) && !empty($validated['media_url'])) {
                if (strpos($validated['media_url'], 'youtube') !== false || strpos($validated['media_url'], 'youtu.be') !== false) {
                    $validated['video_source'] = 'youtube';
                } elseif (strpos($validated['media_url'], 'vimeo') !== false) {
                    $validated['video_source'] = 'vimeo';
                } else {
                    $validated['video_source'] = 'direct';
                }
            }
        } elseif ($validated['context_type'] === 'audio') {
            $this->deleteMedia($questionContext);
            $validated['content'] = null;
            $validated['video_source'] = null;
            // For audio, we use media_url to avoid redundancy
            if ($request->hasFile('media_file')) {
                $path = $request->file('media_file')->store('course-assets', 'public');
                $validated['media_url'] = '/storage/' . $path;
            }
            $validated['audio_url'] = null;
        } elseif ($validated['context_type'] === 'image_with_audio') {
            $validated['content'] = null;
            $validated['video_source'] = null;
        }

        $questionContext->update($validated);

        return new QuestionContextResource($questionContext);
    }

    private function deleteFiles(QuestionContext $context)
    {
        $this->deleteMedia($context);
        $this->deleteAudio($context);
    }

    private function deleteMedia(QuestionContext $context)
    {
        if ($context->media_url && str_starts_with($context->media_url, '/storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $context->media_url));
        }
    }

    private function deleteAudio(QuestionContext $context)
    {
        if ($context->audio_url && str_starts_with($context->audio_url, '/storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('/storage/', '', $context->audio_url));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course, QuestionContext $questionContext)
    {
        if ($questionContext->course_id !== $course->id) {
            abort(404);
        }

        $this->deleteFiles($questionContext);
        $questionContext->delete();

        return response()->noContent();
    }

    /**
     * Attach questions to the context.
     */
    public function attachQuestions(Request $request, Course $course, QuestionContext $questionContext)
    {
        if ($questionContext->course_id !== $course->id) {
            abort(404);
        }

        $validated = $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'exists:questions,id',
        ]);

        // Ensure questions belong to the same course
        $questions = \App\Models\Question::whereIn('id', $validated['question_ids'])
            ->where('course_id', $course->id)
            ->get();

        foreach ($questions as $question) {
            $question->update(['question_context_id' => $questionContext->id]);
        }

        return response()->json(['message' => 'Questions attached successfully']);
    }

    /**
     * Detach questions from the context.
     */
    public function detachQuestion(Course $course, QuestionContext $questionContext, \App\Models\Question $question)
    {
        if ($questionContext->course_id !== $course->id || $question->question_context_id !== $questionContext->id) {
            abort(404);
        }

        $question->update(['question_context_id' => null]);

        return response()->json(['message' => 'Question detached successfully']);
    }
}
