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
        'amount',
        'date',
        'note',
    ];

    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }

    
}
