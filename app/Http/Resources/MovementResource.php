<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'direction' => $this->direction,
            'quantity' => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reason' => $this->reason,
            'product_name' => $this->product ? $this->product->name : 'Producto Eliminado',
            'product_sku' => $this->product ? $this->product->sku : null,
            'user_name' => $this->user ? $this->user->name : null,
            'seller_name' => $this->seller ? $this->seller->name : null,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
