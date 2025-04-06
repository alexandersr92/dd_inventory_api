<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Credit extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'user_id',
        'organization_id',
        'store_id',
        'client_id',
        'invoice_id',
        'total',
        'debt',
        'current',
        'credit_status',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creditDetails()
    {
        return $this->hasMany(CreditDetail::class);
    }
}

