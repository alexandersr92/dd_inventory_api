<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;

class Product extends Model
{
    use Uuids;
    use HasFactory;
    use Multitenantable;

    protected $guarded = [];

    protected $fillable = [
        'product_usage',
        'material_profile',
        'name',
        'barcode',
        'sku',
        'unit_of_measure',
        'image',
        'description',
        'cost',
        'price',
        'min_stock',
        'status',
        'organization_id',
    ];

    protected $attributes = ['product_usage' => 'sale'];
    protected $casts = ['material_profile' => 'array', 'cost' => 'float'];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (!in_array($product->product_usage, ['sale', 'supply', 'both'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['product_usage' => 'Uso de producto no válido.']);
            }
            $product->material_profile = \App\Services\MaterialUnits::validate($product->material_profile, $product->unit_of_measure ?? 'unit');
            if ($product->exists && $product->isDirty('unit_of_measure') &&
                \App\Services\MaterialUnits::canonical($product->getOriginal('unit_of_measure')) !== \App\Services\MaterialUnits::canonical($product->unit_of_measure) &&
                ($product->inventoryDetails()->where('quantity', '!=', 0)->exists() || $product->purchaseDetails()->exists() || \App\Models\InventoryMovement::where('product_id', $product->id)->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['unit_of_measure' => 'No puedes cambiar la unidad base de un producto con existencias o compras históricas.']);
            }
        });
    }

    public function scopeSellable(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn('product_usage', ['sale', 'both']);
    }

    public function scopeMaterial(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn('product_usage', ['supply', 'both']);
    }

    public function assertSellable(): void
    {
        if (!in_array($this->product_usage, ['sale', 'both'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['products' => 'El producto '.$this->name.' es un insumo y no se puede vender.']);
        }
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }


    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function meta()
    {
        return $this->hasMany(ProductMeta::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class);
    }

    public function inventoryDetails()
    {
        return $this->hasMany(InventoryDetail::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
