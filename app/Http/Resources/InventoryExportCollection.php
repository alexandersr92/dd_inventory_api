<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InventoryExportCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($inventoryDetail) {

            $tagsToString = $inventoryDetail->product->tags->pluck('name')->implode(', ');
            $categoriesToString = $inventoryDetail->product->categories->pluck('name')->implode(', ');
            $inventoryDetail->product->tags = $tagsToString;
            $inventoryDetail->product->categories = $categoriesToString;

            return [
    
                'product_name' => $inventoryDetail->product->name,
                'quantity' => $inventoryDetail->quantity,
                'status' => $inventoryDetail->status,
                'price' => $inventoryDetail->price,
                'cost' => $inventoryDetail->product->cost, 
                'barcode' => $inventoryDetail->product->barcode,
                'sku' => $inventoryDetail->product->sku,
                'tags' =>   $inventoryDetail->product->tags,
                'category' => $inventoryDetail->product->categories



            ];
        })->toArray();
    }
}
