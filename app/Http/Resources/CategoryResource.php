<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $loadedChildren = null;
        if ($this->relationLoaded('allChildren')) {
            $loadedChildren = $this->allChildren;
        } elseif ($this->relationLoaded('children')) {
            $loadedChildren = $this->children;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'organization_id' => $this->organization_id,
            'parent_id' => $this->parent_id,
            'level' => (int) $this->level,
            'sort_order' => (int) $this->sort_order,
            'status' => $this->status,
            'full_path' => $this->full_path,
            'children' => $loadedChildren !== null ? CategoryResource::collection($loadedChildren) : [],
            'products_count' => $this->whenCounted('products', $this->products_count),
            'children_count' => $this->whenCounted('children', $this->children_count),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
