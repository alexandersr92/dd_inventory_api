<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationLicense extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'organization_id',
        'type',
        'days',
        'previous_expires_at',
        'new_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_expires_at' => 'datetime',
            'new_expires_at' => 'datetime',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
