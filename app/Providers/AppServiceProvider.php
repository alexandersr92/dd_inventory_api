<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Register Policies
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Store::class, \App\Policies\StorePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Product::class, \App\Policies\ProductPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Invoice::class, \App\Policies\InvoicePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Purchases::class, \App\Policies\PurchasesPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Inventory::class, \App\Policies\InventoryPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Supplier::class, \App\Policies\SupplierPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Seller::class, \App\Policies\SellerPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Report::class, \App\Policies\ReportPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Setting::class, \App\Policies\SettingPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Role::class, \App\Policies\RolePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Client::class, \App\Policies\ClientPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Credit::class, \App\Policies\CreditPolicy::class);
    }
}
