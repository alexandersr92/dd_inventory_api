<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $apiUrl = config('app.url');

      
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'status' => $this->status,
            'zip' => $this->zip,
            'status' => $this->status,
            'store_currency' => $this->store_currency,
            'ruc' => $this->ruc,
            'print_logo' => $this->print_logo ? $apiUrl . '/storage/' . $this->print_logo : null,
         
            'print_header' => $this->print_header,
            'print_footer' => $this->print_footer,
            'print_note' => $this->print_note,
            'print_width' => $this->print_width,
            'invoice_number' => $this->invoice_number,
            'invoice_prefix' => $this->invoice_prefix,


        ];
    }
}
