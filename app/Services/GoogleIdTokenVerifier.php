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
        $clientId = config('services.google.client_id');
        if (empty($clientId)) {
            throw new RuntimeException('Google OAuth no está configurado (client_id ausente).');
        }

        try {
            $keys = JWK::parseKeySet($this->fetchCerts());
            // JWT::decode valida firma + exp + nbf/iat.
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            throw new RuntimeException('ID token de Google inválido o expirado.', 0, $e);
        }

        // aud: cierra el confused-deputy — el token debe haber sido emitido para NUESTRO client_id.
        $aud = $claims['aud'] ?? null;
        if ($aud !== $clientId) {
            throw new RuntimeException('El ID token no fue emitido para esta aplicación.');
        }

        // iss
        $iss = $claims['iss'] ?? null;
        if (!in_array($iss, self::ALLOWED_ISS, true)) {
            throw new RuntimeException('Emisor del ID token no confiable.');
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
