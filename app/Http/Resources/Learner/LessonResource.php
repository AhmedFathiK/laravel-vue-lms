<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_free' => $this->is_free,
            'thumbnail' => $this->thumbnail,
            'video_url' => $this->video_url,
            // Include related slides using the SlideResource
            'slides' => SlideResource::collection($this->whenLoaded('slides')),
            // Include level if needed, or other relations
            'level' => $this->whenLoaded('level'),
            // Add other fields from user's JSON if missing
            'status' => $this->status,
            'reshow_incorrect_slides' => $this->reshow_incorrect_slides, // snake_case from model? user JSON had reshowIncorrectSlides (camelCase).
            // Let's check Lesson model or DB column. Usually snake_case in DB.
            // If we want camelCase output, we map it here.
            // My frontend code uses `lesson.value.reshow_incorrect_slides` (snake_case) in one place:
            // "if (lesson.value.reshow_incorrect_slides) { reshowQueue.value.push(currentSlide.value) }"
            // So I should return snake_case.
            'reshow_count' => $this->reshow_count,
            'require_correct_answers' => $this->require_correct_answers,
            'course_main_locale' => $this->level->course->main_locale ?? 'en-US',
        ];
    }
}
