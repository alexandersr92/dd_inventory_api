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

        $thisDoamin = env('APP_URL') . '/storage';
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'image' =>  $thisDoamin . '/' . $this->image,
            'price' => $this->price,
            'stock' => 1231,
            'min_stock' => $this->min_stock,
            'unit_of_measure' => $this->unit_of_measure,
            'categories' => $categories,
            'suppliers' => $this->suppliers,
            'tags' => $tags
        ];
    }
}
