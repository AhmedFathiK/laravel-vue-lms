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
            'iconUrl' => $this->icon_url,
            'courseId' => $this->course_id,
            'triggerType' => $this->trigger_type,
            'triggerRepeatCount' => $this->trigger_repeat_count,
            'requirements' => $this->requirements,
            'points' => $this->points,
            'pointsThreshold' => $this->points_threshold,
            'rarity' => $this->rarity,
            'isHidden' => $this->is_hidden,
            'isActive' => $this->is_active,
            'recipientsCount' => $this->recipients_count,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
