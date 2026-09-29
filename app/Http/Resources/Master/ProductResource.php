<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'principal' => $this->whenLoaded('principal', fn () => [
                'id' => $this->principal->id,
                'name' => $this->principal->name,
            ]),
            'name' => $this->name,
            'sku' => $this->sku,
            'type' => $this->type,
            'description' => $this->description,
            'price' => (float) $this->price,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
