<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // FIXME: N+1 Query problem - debería usar eager loading con with(['tags', 'categories', 'inventoryDetails'])
        $tags = $this->tags->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
            ];
        });

        $categories = $this->categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        });

        $imageURL = env('APP_URL') . '/storage'  . '/' . $this->image;

        $inventory = $this->inventoryDetails->map(function ($inventory) {
            return [
                'id' => $inventory->id,
                'quantity' => $inventory->quantity,
                'inventory_id' => $inventory->inventory_id,
                'price' => $inventory->price,
            ];
        });

        $totalStock = $this->inventoryDetails->sum('quantity');

        $supplier = $this->suppliers->first();
        if (!$supplier) {
            $lastPurchaseDetail = $this->purchaseDetails->last();
            $supplier = ($lastPurchaseDetail && $lastPurchaseDetail->purchase)
                ? $lastPurchaseDetail->purchase->supplier
                : null;
        }
 
       // dd($this);
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'image' =>  $this->image ? $imageURL : null,
            'cost' => $this->cost,
            'price' => $this->price,
            'stock' => $totalStock,
            'min_stock' => $this->min_stock,
            'unit_of_measure' => $this->unit_of_measure,
            'category_id' => $this->category_id ?? ($this->categories->first() ? $this->categories->first()->id : null),
            'category_name' => $this->category ? $this->category->full_path : ($this->categories->first() ? $this->categories->first()->name : null),
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'full_path' => $this->category->full_path,
            ] : null,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand ? $this->brand->name : null,
            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null,
            'categories' => $categories,
            'supplier' => $supplier,
            'suppliers' => $supplier,
            'supplier_id' => $supplier ? $supplier->id : null,
            'tags' => $tags,
            'inventory' => $inventory,
        ];
    }
}
