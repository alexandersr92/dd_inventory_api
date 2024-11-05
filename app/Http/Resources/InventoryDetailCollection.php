<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InventoryDetailCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($inventoryDetail) {
            return [
                'id' => $inventoryDetail->id,
                'product_id' => $inventoryDetail->product_id,
                'product' => $inventoryDetail->product->name,
                'quantity' => $inventoryDetail->quantity,
                'status' => $inventoryDetail->status,
                'price' => $inventoryDetail->price,
                'barcode' => $inventoryDetail->product->barcode,
                'sku' => $inventoryDetail->product->sku,
                'tags' => $inventoryDetail->product->tags,
                'category' => $inventoryDetail->product->categories



            ];
        })->toArray();
    }
}
