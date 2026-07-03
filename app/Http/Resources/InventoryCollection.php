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
            $stores = $inventory->stores;
            $firstStore = $stores->first() ?? $inventory->store;

            return [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'store' => $firstStore ? $firstStore->name : null,
                'store_id' => $firstStore ? $firstStore->id : null,
                'address' => $inventory->address,
                'productsQuantity' => $inventory->inventoryDetails->count(),
            ];
        })->toArray();
    }
}
