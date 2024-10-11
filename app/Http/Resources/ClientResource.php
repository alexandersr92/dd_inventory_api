<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        //has creditos
        dd($this);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'status' => $this->is_active,
            'wholesaler' => $this->wholesaler,
            'has_credit' => true,
            'notes' => $this->notes,
            'stores' => $this->stores,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
