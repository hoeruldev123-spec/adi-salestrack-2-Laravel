<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'opportunity' => $this->whenLoaded('opportunity', fn () => [
                'id' => $this->opportunity->id,
                'title' => $this->opportunity->title,
                'principal' => $this->opportunity->principal ? [
                    'id' => $this->opportunity->principal->id,
                    'name' => $this->opportunity->principal->name,
                ] : null,
            ]),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'sales' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'transaction_date' => $this->transaction_date?->toDateString(),
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
