<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class Store extends Model
{
    use HasFactory;
    use Uuids;

    protected $fillable = [
        'name',
        'description',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'status',
        'store_currency',
        'organization_id',
        'ruc',
        'print_logo',
        'print_header',
        'print_footer',
        'print_note',
        'print_width',
        'invoice_number',
        'invoice_prefix',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function sellers()
    {
        return $this->hasMany(Seller::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
