<?php

namespace App\Http\Resources\Learner;

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
            'correct_answer_feedback' => $this->correct_answer_feedback,
            'incorrect_answer_feedback' => $this->incorrect_answer_feedback,
            'correct_sentence' => $this->correct_sentence,
            'correct_sentence_translation' => $this->correct_sentence_translation,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'audio_url' => $this->audio_url,
        ];
    }
}
