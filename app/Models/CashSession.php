<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class CashSession extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'store_id',
        'user_id',
        'cash_register_name',
        'opening_balance',
        'expected_balance',
        'actual_cash',
        'actual_usd',
        'expected_usd',
        'usd_exchange_rate',
        'difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'float',
        'expected_balance' => 'float',
        'actual_cash' => 'float',
        'actual_usd' => 'float',
        'expected_usd' => 'float',
        'usd_exchange_rate' => 'float',
        'difference' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditDetails()
    {
        return $this->hasMany(CreditDetail::class);
    }

    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class);
    }
}
