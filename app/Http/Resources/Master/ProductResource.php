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
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'category_id' => $this->category_id,
            'name' => $this->name,
            'product_type' => $this->product_type,
            'sku' => $this->sku,
            'type' => $this->type,
            'description' => $this->description,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'specifications' => ProductSpecificationResource::collection($this->whenLoaded('specifications')),
            'price_lists' => PriceListResource::collection($this->whenLoaded('priceLists')),
            'specifications_count' => $this->whenCounted('specifications'),
            'price_lists_count' => $this->whenCounted('priceLists'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
