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

        // Forzar HTTPS en la generación de URLs si la petición actual es segura (evita error de "Formulario no seguro" en el navegador)
        if (request()->secure() || request()->header('X-Forwarded-Proto') === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Correo de verificación en español (reemplaza la plantilla por defecto de Laravel).
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Verifica tu correo · DipleBill')
                ->greeting('¡Hola' . ($notifiable->name ? ' ' . $notifiable->name : '') . '!')
                ->line('Gracias por registrarte en DipleBill. Confirma tu correo para asegurar tu cuenta y recibir los avisos de tu licencia y pagos.')
                ->action('Verificar mi correo', $url)
                ->line('El enlace vence en 60 minutos. Si no creaste esta cuenta, puedes ignorar este mensaje.')
                ->salutation('— El equipo de DipleBill');
        });

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
        \Illuminate\Support\Facades\Gate::policy(\App\Models\InventoryMovement::class, \App\Policies\InventoryMovementPolicy::class);


        // Registro del dispatcher dinámico de notificaciones para los eventos del sistema
        \Illuminate\Support\Facades\Event::listen([
            \App\Events\ClientRegistered::class,
            \App\Events\BoxClosed::class,
            \App\Events\UserCreated::class,
            \App\Events\InvoiceCreated::class,
            \App\Events\CreditCreated::class,
        ], \App\Listeners\NotificationDispatcher::class);

        // Registro de observador de stock para WooCommerce
        \App\Models\InventoryDetail::observe(\App\Observers\InventoryDetailObserver::class);
    }
}

