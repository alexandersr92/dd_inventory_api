<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CreditByClientCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($credit) {

         
            return [
                'client_id' => $credit->client_id,
                'client_name' => $credit->client_name,
                'invoices_qty' => $credit->invoices_qty,
                'total_debt' => $credit->total_debt,
                'total_paid' => $credit->total_paid,
                'total_unpaid' => $credit->total_unpaid,
                'created_at' => $credit->created_at,
                'updated_at' => $credit->updated_at,
                
            ];
        })->toArray();
    }
}
