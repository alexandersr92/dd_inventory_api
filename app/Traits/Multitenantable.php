<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use App\Services\TenantManager;

trait Multitenantable
{
    /**
     * Boot the multitenantable trait for a model.
     */
    public static function bootMultitenantable(): void
    {
        // Automatically set organization_id when creating a record
        static::creating(function ($model) {
            if (TenantManager::getTenantId()) {
                $model->organization_id = TenantManager::getTenantId();
            }
        });

        // Automatically filter queries by organization_id
        static::addGlobalScope('organization', function (Builder $builder) {
            $tenantId = TenantManager::getTenantId();
            if ($tenantId) {
                $builder->where($builder->getQuery()->from . '.organization_id', $tenantId);
            }
        });
    }
}
