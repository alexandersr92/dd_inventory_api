<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;

class ProductRelease extends Model
{
    use HasFactory;
    use Uuids;

    protected $connection = 'central';
    protected $table = 'product_releases';

    protected $fillable = [
        'version',
        'title',
        'body',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Scope a query to only include published releases that are not scheduled for future.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
