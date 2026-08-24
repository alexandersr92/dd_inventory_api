<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifica un ID token de Google (JWT firmado) del lado del servidor.
 *
 * Antes, el login usaba Socialite::userFromToken() con un *access token*, que
 * Google acepta sin importar para qué app fue emitido → confused-deputy: un
 * access token emitido para CUALQUIER otra app servía para autenticarse aquí.
 *
 * Esta clase exige un ID token y valida:
 *   - firma (contra las claves públicas de Google / JWKS)
 *   - exp / iat (via Firebase\JWT)
 *   - aud === nuestro client_id  (cierra el confused-deputy)
 *   - iss === accounts.google.com
 *   - email_verified === true
 */
class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const CERTS_CACHE_KEY = 'google_oauth_jwks';
    private const ALLOWED_ISS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @return array{sub:string,email:string,name:?string,picture:?string}
     * @throws RuntimeException si el token no es válido para esta app.
     */
    public function verify(string $idToken): array
    {
        $configClientId = config('services.google.client_id');
        $envClientId = env('GOOGLE_CLIENT_ID');
        $dbClientId = null;
        try {
            $dbClientId = \App\Models\GlobalSetting::where('key', 'google_client_id')->value('value');
        } catch (\Throwable $e) {
            // DB not reachable or table missing
        }

        $allRaw = array_filter([$configClientId, $envClientId, $dbClientId]);
        $allowedClientIds = [];
        foreach ($allRaw as $raw) {
            foreach (explode(',', (string) $raw) as $id) {
                $trimmed = trim($id);
                if (!empty($trimmed) && !in_array($trimmed, $allowedClientIds, true)) {
                    $allowedClientIds[] = $trimmed;
                }
            }
        }

        if (empty($allowedClientIds)) {
            \Illuminate\Support\Facades\Log::error('GoogleIdTokenVerifier: Google OAuth no está configurado (client_id ausente).');
            throw new RuntimeException('Google OAuth no está configurado en el backend (client_id ausente).');
        }

        try {
            $keys = JWK::parseKeySet($this->fetchCerts());
            // JWT::decode valida firma + exp + nbf/iat.
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GoogleIdTokenVerifier: JWT decode error: ' . $e->getMessage(), [
                'exception' => $e->getMessage()
            ]);
            throw new RuntimeException('ID token de Google inválido o expirado (' . $e->getMessage() . ').', 0, $e);
        }

        // aud / azp: cierra el confused-deputy — el token debe haber sido emitido para NUESTRO client_id.
        $aud = $claims['aud'] ?? null;
        $azp = $claims['azp'] ?? null;

        $tokenAudiences = array_filter(array_merge(
            is_array($aud) ? $aud : [$aud],
            is_array($azp) ? [$azp] : []
        ));

        $matched = false;
        foreach ($allowedClientIds as $allowedId) {
            if (in_array($allowedId, $tokenAudiences, true)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            \Illuminate\Support\Facades\Log::warning('GoogleIdTokenVerifier: Audience mismatch', [
                'token_aud' => $aud,
                'token_azp' => $azp,
                'allowed_client_ids' => $allowedClientIds,
            ]);
            $received = is_array($aud) ? implode(', ', $aud) : (string)$aud;
            throw new RuntimeException('El ID token no fue emitido para esta aplicación (Client ID recibido: ' . $received . ').');
        }

        // iss
        $iss = $claims['iss'] ?? null;
        if (!in_array($iss, self::ALLOWED_ISS, true)) {
            \Illuminate\Support\Facades\Log::warning('GoogleIdTokenVerifier: Issuer mismatch', [
                'token_iss' => $iss,
                'allowed_iss' => self::ALLOWED_ISS,
            ]);
            throw new RuntimeException('Emisor del ID token no confiable (' . $iss . ').');
        }

        // email verificado
        $email = $claims['email'] ?? null;
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (empty($email) || !$emailVerified) {
            throw new RuntimeException('El correo de Google no está verificado.');
        }

        $sub = $claims['sub'] ?? null;
        if (empty($sub)) {
            throw new RuntimeException('El ID token no contiene un identificador de usuario.');
        }

        return [
            'sub' => (string) $sub,
            'email' => (string) $email,
            'name' => isset($claims['name']) ? (string) $claims['name'] : null,
            'picture' => isset($claims['picture']) ? (string) $claims['picture'] : null,
        ];
    }

    /**
     * Descarga (y cachea ~1h) el JWKS de Google. Respeta Cache-Control si viene.
     */
    private function fetchCerts(): array
    {
        return Cache::remember(self::CERTS_CACHE_KEY, now()->addMinutes(60), function () {
            $response = Http::timeout(8)->get(self::CERTS_URL);
            if (!$response->successful()) {
                throw new RuntimeException('No se pudieron obtener las claves públicas de Google.');
            }
            return $response->json();
        });
    }
}
