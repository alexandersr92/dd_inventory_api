<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InventoryDetailCollection;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stores = $this->stores;
        $firstStore = $stores->first() ?? $this->store;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'store' => $firstStore ? $firstStore->name : null,
            'store_id' => $firstStore ? $firstStore->id : null,
            'stores' => StoreResource::collection($this->whenLoaded('stores')),
            'store_ids' => $stores->pluck('id')->toArray(),
            'address' => $this->address,
            'description' => $this->description,
        ];
    }
}
