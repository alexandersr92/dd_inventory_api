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
                'name' => $inventoryDetail->product->name,
                'quantity' => $inventoryDetail->quantity,
                'status' => $inventoryDetail->status,
                'price' => $inventoryDetail->price,
                'barcode' => $inventoryDetail->product->barcode,
                'sku' => $inventoryDetail->product->sku,
                'product_usage' => $inventoryDetail->product->product_usage,
                'material_profile' => $inventoryDetail->product->material_profile,
                'unit_of_measure' => $inventoryDetail->product->unit_of_measure,
                'cost' => (float) $inventoryDetail->product->cost,
                'tags' => $inventoryDetail->product->tags,
                'categories' => $inventoryDetail->product->categories,
            ];
        })->toArray();
    }
}
