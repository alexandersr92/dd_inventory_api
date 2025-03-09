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
                'client' => $credit->client,
                'invoices_qty' => $credit->invoices_qty,
                'total_credit' => $credit->total_credit,
                'created_at' => $credit->created_at,
                'updated_at' => $credit->updated_at,
                
            ];
        })->toArray();
    }
}
