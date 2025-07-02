<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrophyResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'icon_url' => $this->icon_url,
            'course_id' => $this->course_id,
            'trigger_type' => $this->trigger_type,
            'trigger_repeat_count' => $this->trigger_repeat_count,
            'requirements' => $this->requirements,
            'points' => $this->points,
            'points_threshold' => $this->points_threshold,
            'rarity' => $this->rarity,
            'is_hidden' => $this->is_hidden,
            'is_active' => $this->is_active,
            'recipients_count' => $this->recipients_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
