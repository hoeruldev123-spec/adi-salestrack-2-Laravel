<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'type' => $this->type,
            'activity_type' => $this->activity_type ?? $this->type,
            'subject' => $this->subject,
            'description' => $this->description,
            'activity_date' => $this->activity_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
