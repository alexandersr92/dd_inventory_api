<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class CreditDetail extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'credit_id',
        'seller_id',
        'amount',
        'date',
        'note',
        'payment_method',
        'payment_metadata',
        'cash_session_id',
    ];

    protected $casts = [
        'payment_metadata' => 'array',
    ];

    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }


    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
    
}
