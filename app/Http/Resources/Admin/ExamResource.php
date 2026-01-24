<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'courseId' => $this->course_id,
            'type' => $this->type,
            'timeLimit' => $this->time_limit,
            'passingPercentage' => $this->passing_percentage,
            'maxAttempts' => $this->max_attempts,
            'isActive' => $this->is_active,
            'randomizeQuestions' => $this->randomize_questions,
            'showAnswers' => $this->show_answers,
            'status' => $this->status,
            'sections' => ExamSectionResource::collection($this->whenLoaded('sections')),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
