<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'opportunity' => $this->whenLoaded('opportunity', fn () => [
                'id' => $this->opportunity->id,
                'title' => $this->opportunity->title,
            ]),
            'field_name' => $this->field_name,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
