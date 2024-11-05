<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'store' => $this->store->name,
            'store_id' => $this->store->id,
            'address' => $this->address,
            'details' => new InventoryDetailCollection($this->inventoryDetails),
        ];
    }
}
