<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SupplierCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($supplier) {

            $contactCount = $supplier->contacts->count();

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_count' => $contactCount,
                'status' => $supplier->status,
                'created_at' => $supplier->created_at,
                'updated_at' => $supplier->updated_at,
            ];
        })->toArray();
    }
}
