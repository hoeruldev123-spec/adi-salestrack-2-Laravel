<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'principal' => $this->whenLoaded('principal', fn () => [
                'id' => $this->principal->id,
                'name' => $this->principal->name,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'value' => (float) $this->value,
            'stage' => $this->stage,
            'status' => $this->status,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'source' => $this->source,
            'items_count' => $this->whenCounted('items'),
            'activities_count' => $this->whenCounted('activities'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
