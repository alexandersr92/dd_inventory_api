<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Credit;

class ClientCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        //has creditos
        return $this->collection->map(function ($client) {


             $has_credit = Credit::where('client_id', $client->id)->exists();
       
            return [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'city' => $client->city,
                'state' => $client->state,
                'status' => $client->status,
                'has_credit' => $has_credit,
                'store_id' => $client->stores->pluck('id'),
                'wholesaler' => $client->wholesaler,
                'created_at' => $client->created_at,
                'updated_at' => $client->updated_at,

            ];
        })->toArray();
    }
}
