<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $questions = $this->whenLoaded('questions');
        $context = null;

        if ($questions instanceof \Illuminate\Database\Eloquent\Collection) {
            $context = $questions->pluck('context')->unique('id')->first();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'order' => $this->order,
            'timeLimit' => $this->time_limit,
            'context' => new QuestionContextResource($context),
            'questions' => QuestionResource::collection($questions),
        ];
    }
}
