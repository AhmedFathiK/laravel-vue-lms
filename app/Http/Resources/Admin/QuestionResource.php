<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'question_text' => $this->question_text,
            'type' => $this->type,
            'content' => $this->content,
            'points' => $this->whenPivotLoaded('exam_section_questions', function () {
                return $this->pivot->points;
            }, $this->points),
            'difficulty' => $this->difficulty,
            'tags' => $this->tags,
            'correct_feedback' => $this->correct_feedback,
            'incorrect_feedback' => $this->incorrect_feedback,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'audio_url' => $this->audio_url,
            'video_source' => $this->video_source,
            'question_context_id' => $this->question_context_id,
            'context' => new QuestionContextResource($this->whenLoaded('context')),
            'terms' => TermResource::collection($this->whenLoaded('terms')),
            'concepts' => $this->whenLoaded('concepts'),
            'order' => $this->whenPivotLoaded('exam_section_questions', function () {
                return $this->pivot->order;
            }),
            'exam_points' => $this->whenPivotLoaded('exam_section_questions', function () {
                return $this->pivot->points;
            }),
        ];
    }
}
