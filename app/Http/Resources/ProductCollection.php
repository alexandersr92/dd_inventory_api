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
            return [
                'id' => $this->id,
                'sku' => $this->sku,
                'barcode' => $this->barcode,
                'name' => $this->name,
                'image' => $this->image,
                'price' => $this->price,
                'stock' => 1231,
                'min_stock' => $this->min_stock,
                'unit_of_masure' => $this->unit_of_masure,
                'category' => CategoryResource::collection($this->category_id),
                'tags' => TagResource::collection($this->tags),
            ];
        })->toArray();
    }
}
