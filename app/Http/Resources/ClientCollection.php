<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClientCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'city' => $client->city,
                'state' => $client->state,
                'is_active' => $client->is_active,
                'wholeasaler' => $client->wholeasaler,
                'created_at' => $client->created_at,
                'updated_at' => $client->updated_at,
            ];
        })->toArray();
    }
}