<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class PaymentSubmission extends Model
{
    use Uuids;

    protected $connection = 'central';

    protected $fillable = [
        'organization_id',
        'plan_id',
        'provider_id',
        'amount',
        'currency',
        'reference',
        'receipt_path',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function provider()
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_id');
    }
}
