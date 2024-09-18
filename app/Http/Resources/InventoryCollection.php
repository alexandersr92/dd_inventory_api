<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InventoryCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        //has creditos
        return $this->collection->map(function ($inventory) {
            return [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'store' => $inventory->store->name,
                'store_id' => $inventory->store->id,
                'address' => $inventory->address,
                'productsQuantity' => $inventory->inventoryDetails->count(),

            ];
        })->toArray();
    }
}
