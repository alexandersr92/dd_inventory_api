<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class CashTransaction extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'cash_session_id',
        'type',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function cashSession()
    {
        return $this->belongsTo(CashSession::class);
    }
}
