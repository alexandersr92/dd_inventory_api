<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($product) {

            $tags = $product->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ];
            });

            $categories = $product->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                ];
            });

            $inventory = $product->inventoryDetails->map(function ($inventory) {
                return [
                    'id' => $inventory->id,
                    'quantity' => $inventory->quantity,
                    'inventory_id' => $inventory->inventory_id,
                 
                ];
            });

            $imageURL = env('APP_URL') . '/storage'  . '/' . $product->image;
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'image' =>  $product->image ? $imageURL : null,
                'cost' => $product->cost,
                'price' => $product->price,
                'stock' => 1231,
                'min_stock' => $product->min_stock,
                'unit_of_measure' => $product->unit_of_measure,
                'categories' => $categories,
                'tags' => $tags,
                'inventory' => $inventory,
            ];
        })->toArray();
    }
}
