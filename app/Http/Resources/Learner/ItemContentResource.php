<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $this can be a Lesson or an Exam (as an array or object)
        // Since the controller currently passes arrays, we handle both.
        $data = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'] ?? ($data['item_type'] ?? 'lesson'),
            'thumbnail' => $data['thumbnail'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'video_type' => $data['video_type'] ?? null,
            'completed' => (bool) ($data['completed'] ?? false),
            'locked' => (bool) ($data['locked'] ?? false),
            'is_paid_locked' => (bool) ($data['is_paid_locked'] ?? false),
            'outcome' => $data['outcome'] ?? null,
        ];
    }
}
