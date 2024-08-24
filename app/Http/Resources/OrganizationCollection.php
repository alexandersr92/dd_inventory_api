<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrganizationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {

        return $this->collection->map(function ($organization) {
            return [
                'id' => $organization->id,
                'name' => $organization->name,
                'logo' => $organization->logo,
                'is_active' => $organization->is_active,
                'owner_id' => $organization->owner_id,

            ];
        })->toArray();
    }
}
