<?php

namespace App\Models;

use App\Traits\Multitenantable;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;

class CustomJobQuote extends Model
{
    use Multitenantable, Uuids;

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['version' => 'integer'];

    public function revisions()
    {
        return $this->hasMany(CustomJobQuoteRevision::class, 'quote_id')->orderByDesc('number');
    }

    public function proforma()
    {
        return $this->hasOne(Invoice::class, 'custom_job_quote_id');
    }
}
