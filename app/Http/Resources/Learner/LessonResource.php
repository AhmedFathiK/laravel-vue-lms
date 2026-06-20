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
            'slides' => SlideResource::collection($this->whenLoaded('slides')),
            'reshow_incorrect_slides' => (bool) $this->reshow_incorrect_slides,
            'reshow_count' => (int) $this->reshow_count,
            'require_correct_answers' => (bool) $this->require_correct_answers,
            'course_id' => $this->level->course_id ?? null,
            'course_main_locale' => $this->level->course->main_locale ?? 'en-US',
        ];
    }
}
