<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $guarded = [];

    protected $fillable = [
        'name',
        'slug',
        'organization_id',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public static function resolveByName(string $name, string $organizationId): ?self
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->first();
    }
}
