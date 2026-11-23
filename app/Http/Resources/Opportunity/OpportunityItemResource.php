<?php

namespace App\Http\Resources\Opportunity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'type' => $this->product->type,
                'product_type' => $this->product->product_type,
            ]),
            'price_list' => $this->whenLoaded('priceList', fn () => $this->priceList ? [
                'id' => $this->priceList->id,
                'pricing_type' => $this->priceList->pricing_type,
                'unit_price' => (float) $this->priceList->unit_price,
                'billing_period' => $this->priceList->billing_period,
            ] : null),
            'price_list_id' => $this->price_list_id,
            'item_description' => $this->item_description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount' => (float) $this->discount,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
