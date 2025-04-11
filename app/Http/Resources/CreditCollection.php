<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CreditCollection extends ResourceCollection
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
                'id' => $credit->id,
                'amount' => $credit->total,
                'invoice_id' => $credit->invoice_id,
                'current_debt' => $credit->debt,
                'client' => $credit->client->name,
                'status' => $credit->credit_status,
                'invoice_number' => $credit->invoice->invoice_number,
                'created_at' => $credit->created_at,
                'updated_at' => $credit->updated_at,
              

            ];
        })->toArray();
    }
}
