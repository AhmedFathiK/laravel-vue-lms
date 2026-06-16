<?php

namespace App\Http\Resources\Learner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'id' => $data['id'],
            'title' => $data['title'],
            'current_user_progress' => $data['current_user_progress'] ?? ($data['currentUserProgress'] ?? null),
            'items' => ItemContentResource::collection($data['items'] ?? []),
        ];
    }
}
