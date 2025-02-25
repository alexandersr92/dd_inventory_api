<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
     
        return $this->collection->map(function ($purchase) {
            return [
            'id' => $purchase->id,
            'store' => $purchase->store->name,
            'supplier' => $purchase->supplier->name,
            'inventory' => $purchase->inventory->name,
            'total' => $purchase->total,
            'purchase_date' => $purchase->purchase_date,
            'purchase_note' => $purchase->purchase_note,
            'total_items' => $purchase->total_items,
            'created_at' => $purchase->created_at,
            'updated_at' => $purchase->updated_at,
            ];
        })->toArray();
    }
}
