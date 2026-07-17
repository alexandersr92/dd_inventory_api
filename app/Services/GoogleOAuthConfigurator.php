<?php

namespace App\Services;

use App\Models\GlobalSetting;

class GoogleOAuthConfigurator
{
    /**
     * Aplica la configuración de Google OAuth guardada en la base de datos central en runtime.
     */
    public static function applyConfiguration(): void
    {
        $clientId = GlobalSetting::where('key', 'google_client_id')->value('value');
        $clientSecret = GlobalSetting::where('key', 'google_client_secret')->value('value');
        $redirectUri = GlobalSetting::where('key', 'google_redirect_uri')->value('value');

        // Aplicar a la configuración de servicios de Laravel en runtime
        config([
            'services.google.client_id' => $clientId ?: config('services.google.client_id'),
            'services.google.client_secret' => $clientSecret ?: config('services.google.client_secret'),
            'services.google.redirect' => $redirectUri ?: config('services.google.redirect'),
        ]);
    }
}
