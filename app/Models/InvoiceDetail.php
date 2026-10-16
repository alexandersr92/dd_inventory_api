<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class InvoiceDetail extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'invoice_id',
        'line_type',
        'custom_job_order_item_id',
        'custom_job_description',
        'custom_job_snapshot',
        'product_id',
        'inventory_id',
        'quantity',
        'price',
        'cost',
        'total',
        'discount',
        'tax',
        'grand_total',
        'sort_order',
    ];

    protected $casts = ['custom_job_snapshot' => 'array'];


    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function customJobOrderItem()
    {
        return $this->belongsTo(CustomJobOrderItem::class, 'custom_job_order_item_id');
    }

/*     public function movements()
    {
        return $this->hasMany(Movement::class);
    } */


}
