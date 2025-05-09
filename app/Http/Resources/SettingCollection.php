<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SettingCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
       return $this->collection->map(function ($setting) {
            return [
                'id'             => $setting->id,
                'type'           => $setting->type,
                'entity_id'      => $setting->entity_id,
                'key'            => $setting->key,
                'value'          => $setting->value,
              
            ];
        })->toArray();
    }
}
