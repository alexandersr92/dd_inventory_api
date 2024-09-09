<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class StoreCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'city' => $store->city,
                'address' => $store->address,
                'phone' => $store->phone,
                'state' => $store->state,
                'country' => $store->country,
                'status' => $store->status,





            ];
        })->toArray();
    }
}
