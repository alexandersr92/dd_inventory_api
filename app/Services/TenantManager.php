<?php

namespace App\Services;

use App\Models\Organization;

class TenantManager
{
    /**
     * The active tenant organization.
     *
     * @var \App\Models\Organization|null
     */
    protected static ?Organization $tenant = null;

    /**
     * Set the active tenant.
     */
    public static function setTenant(Organization $tenant): void
    {
        self::$tenant = $tenant;
    }

    /**
     * Get the active tenant.
     */
    public static function getTenant(): ?Organization
    {
        return self::$tenant;
    }

    /**
     * Get the active tenant ID.
     */
    public static function getTenantId(): ?string
    {
        return self::$tenant?->id;
    }

    /**
     * Clear the active tenant.
     */
    public static function clear(): void
    {
        self::$tenant = null;
    }
}
