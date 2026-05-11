<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Uuids;

class Report extends Model
{
    use HasFactory, Uuids;

    protected $fillable = [
        'organization_id',
        'store_id',
        'user_id',
        'name',
        'type',
        'filters',
        'file_path',
        'status',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
