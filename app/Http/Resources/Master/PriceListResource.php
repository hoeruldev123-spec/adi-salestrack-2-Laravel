<?php

namespace App\Http\Resources\Master;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'product_id' => $this->product_id,
            'pricing_type' => $this->pricing_type,
            'unit_price' => (float) $this->unit_price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'effective_date' => $this->effective_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
