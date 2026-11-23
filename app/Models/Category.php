<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Uuids;
use App\Traits\Multitenantable;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    use Uuids;
    use Multitenantable;

    protected $guarded = [];

    protected $fillable = [
        'name',
        'slug',
        'organization_id',
        'parent_id',
        'level',
        'sort_order',
        'status',
    ];

    protected $appends = [
        'full_path',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            if ($category->parent_id) {
                $parent = static::withoutGlobalScopes()->find($category->parent_id);
                $category->level = $parent ? $parent->level + 1 : 1;
            } else {
                $category->level = 1;
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function legacyProducts()
    {
        return $this->belongsToMany(Product::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Compute full path like "Ropa > Camisas > Manga corta"
     */
    public function getFullPathAttribute(): string
    {
        $names = [$this->name];
        $current = $this;

        while ($current->parent_id) {
            // Use relation if already loaded, otherwise fetch
            if ($current->relationLoaded('parent') && $current->parent) {
                $current = $current->parent;
            } else {
                $current = static::withoutGlobalScopes()->find($current->parent_id);
            }

            if (!$current) {
                break;
            }
            array_unshift($names, $current->name);
        }

        return implode(' > ', $names);
    }

    /**
     * Resolve a category by either full path ("Ropa > Camisas") or single name.
     */
    public static function resolveByPathOrName(string $input, string $organizationId): ?self
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // 1. If path contains '>'
        if (str_contains($input, '>')) {
            $parts = array_values(array_filter(array_map('trim', explode('>', $input))));
            if (empty($parts)) {
                return null;
            }

            $currentParentId = null;
            $currentCategory = null;

            foreach ($parts as $index => $partName) {
                $query = static::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($partName)]);

                if ($index === 0) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $currentParentId);
                }

                $found = $query->first();
                if (!$found) {
                    return null;
                }

                $currentParentId = $found->id;
                $currentCategory = $found;
            }

            return $currentCategory;
        }

        // 2. Exact match by name (case-insensitive)
        $candidates = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($input)])
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // If multiple candidates with same name in different branches, return root one if any
        $root = $candidates->firstWhere('parent_id', null);
        if ($root) {
            return $root;
        }

        return $candidates->first();
    }
}
