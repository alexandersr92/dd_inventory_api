<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ProductUsageObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Product $product): void
    {
        if (! $product->wasChanged('product_usage')) {
            return;
        }
        foreach ($product->inventoryDetails()->with('inventory')->get() as $detail) {
            $detail->setRelation('product', $product);
            app(InventoryDetailObserver::class)->sync($detail);
        }
    }
}
