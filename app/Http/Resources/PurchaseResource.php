<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {   


      $products = $this->purchaseDetails->map(function($purchaseDetail) {
            return [
                'id' => $purchaseDetail->id,
                'product_id' => $purchaseDetail->product_id,
                'product_name' => $purchaseDetail->product->name,
                'quantity' => $purchaseDetail->quantity,
                'price' => $purchaseDetail->price,
            ];
        })->toArray(); 
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'store_name' => $this->store->name,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier->name,
            'inventory_id' => $this->inventory_id,
            'inventory_name' => $this->inventory->name,
            'total' => $this->total,
            'purchase_date' => $this->purchase_date,
            'purchase_note' => $this->purchase_note,
            'status' => $this->status,
            'total_items' => $this->total_items,
            'products' =>       $products,  
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
