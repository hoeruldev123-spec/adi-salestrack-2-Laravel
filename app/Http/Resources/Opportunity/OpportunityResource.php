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
            'sales' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'principal' => $this->whenLoaded('principal', fn () => $this->principal ? [
                'id' => $this->principal->id,
                'name' => $this->principal->name,
            ] : null),
            'title' => $this->title,
            'project_name' => $this->project_name,
            'client_name' => $this->client_name,
            'description' => $this->description,
            'value' => (float) $this->value,
            'estimated_value' => $this->estimated_value !== null ? (float) $this->estimated_value : (float) $this->value,
            'stage' => $this->stage,
            'progress' => $this->progress,
            'status' => $this->status,
            'probability' => $this->probability,
            'sa_pic' => $this->sa_pic,
            'license_type' => $this->license_type,
            'deal_registration' => $this->deal_registration,
            'closing_date' => $this->closing_date?->toDateString() ?: $this->expected_close_date?->toDateString(),
            'expected_close_date' => $this->expected_close_date?->toDateString() ?: $this->closing_date?->toDateString(),
            'actual_closing_date' => $this->actual_closing_date?->toDateString(),
            'source' => $this->source,
            'remarks' => $this->remarks,
            'items_count' => $this->whenCounted('items'),
            'activities_count' => $this->whenCounted('activities'),
            'items' => OpportunityItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
