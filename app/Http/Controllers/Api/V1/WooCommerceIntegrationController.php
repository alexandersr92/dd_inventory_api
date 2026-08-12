<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WooCommerceIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class WooCommerceIntegrationController extends Controller
{
    /**
     * Obtener la integración activa de WooCommerce de la organización
     */
    public function index()
    {
        $this->assertOwner();

        $orgId = Auth::user()->organization_id;
        $integration = WooCommerceIntegration::where('organization_id', $orgId)->first();

        if (!$integration) {
            return response()->json([
                'status' => 'not_configured',
                'integration' => null
            ]);
        }

        return response()->json([
            'status' => 'configured',
            'integration' => $integration
        ]);
    }

    /**
     * Crear o actualizar la integración de WooCommerce
     */
    public function store(Request $request)
    {
        $this->assertOwner();

        $request->validate([
            'store_id' => 'required|string|exists:stores,id',
            'inventory_id' => 'required|string|exists:inventories,id',
            'woo_store_url' => 'required|url',
            'woo_consumer_key' => 'required|string',
            'woo_consumer_secret' => 'required|string',
            'status' => 'nullable|boolean'
        ]);

        // SEGURIDAD (SSRF): validar que la URL no apunte a la red interna.
        $this->assertSafeWooUrl($request->woo_store_url);

        $orgId = Auth::user()->organization_id;

        $integration = WooCommerceIntegration::updateOrCreate(
            ['organization_id' => $orgId],
            [
                'store_id' => $request->store_id,
                'inventory_id' => $request->inventory_id,
                'woo_store_url' => rtrim($request->woo_store_url, '/'),
                'woo_consumer_key' => $request->woo_consumer_key,
                'woo_consumer_secret' => $request->woo_consumer_secret,
                'status' => $request->input('status', true)
            ]
        );

        return response()->json([
            'message' => 'Integración de WooCommerce guardada correctamente.',
            'integration' => $integration
        ]);
    }

    /**
     * Probar la conexión con la API de WooCommerce
     */
    public function testConnection(Request $request)
    {
        $this->assertOwner();

        $request->validate([
            'woo_store_url' => 'required|url',
            'woo_consumer_key' => 'required|string',
            'woo_consumer_secret' => 'required|string',
        ]);

        // SEGURIDAD (SSRF): bloquear direcciones internas antes de la petición.
        $this->assertSafeWooUrl($request->woo_store_url);

        $url = rtrim($request->woo_store_url, '/');
        $key = $request->woo_consumer_key;
        $secret = $request->woo_consumer_secret;

        try {
            // Realizar una petición simple al endpoint de system status.
            // Sin seguir redirects (evita rebote a un host interno).
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->withBasicAuth($key, $secret)
                ->get($url . '/wp-json/wc/v3/system_status');

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa con WooCommerce. API responde correctamente.'
                ]);
            }

            // SEGURIDAD: no reflejar el body de la respuesta (fuga de datos internos vía SSRF).
            return response()->json([
                'success' => false,
                'message' => 'Error de conexión. Respuesta HTTP: ' . $response->status(),
            ], 400);

        } catch (\Throwable $e) {
            // SEGURIDAD: no reflejar el mensaje de la excepción (fuga de red interna).
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo establecer conexión con el servidor. Valida la URL y que no haya restricciones de firewall.',
            ], 500);
        }
    }

    /**
     * Solo el propietario de la organización gestiona integraciones (contienen
     * credenciales de la API de WooCommerce).
     */
    private function assertOwner(): void
    {
        $user = Auth::user();
        if (!$user->organization || $user->id !== $user->organization->owner_id) {
            abort(Response::HTTP_FORBIDDEN, 'Solo el propietario puede gestionar la integración de WooCommerce.');
        }
    }

    /**
     * Rechaza URLs que apunten a la red interna / metadata cloud (SSRF), que
     * traigan credenciales, o query/fragment (que neutralizarían el path fijo).
     */
    private function assertSafeWooUrl(string $rawUrl): void
    {
        $parts = parse_url($rawUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'URL de la tienda inválida.');
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La URL debe usar http(s).');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La URL no puede contener credenciales.');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La URL no puede contener query ni fragmento.');
        }

        $host = $parts['host'];
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];
            $aaaa = @dns_get_record($host, DNS_AAAA) ?: [];
            foreach ($aaaa as $rec) {
                if (!empty($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }
        if (empty($ips)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No se pudo resolver el host de la tienda.');
        }
        foreach ($ips as $ip) {
            // NO_PRIV_RANGE + NO_RES_RANGE bloquean 10/8, 172.16/12, 192.168/16,
            // 127/8 (loopback), 169.254/16 (metadata cloud), fc00::/7, etc.
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'La URL apunta a una dirección de red no permitida.');
            }
        }
    }
}
