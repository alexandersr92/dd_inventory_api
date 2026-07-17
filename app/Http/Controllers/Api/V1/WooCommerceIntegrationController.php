<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WooCommerceIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WooCommerceIntegrationController extends Controller
{
    /**
     * Obtener la integración activa de WooCommerce de la organización
     */
    public function index()
    {
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
        $request->validate([
            'store_id' => 'required|string|exists:stores,id',
            'inventory_id' => 'required|string|exists:inventories,id',
            'woo_store_url' => 'required|url',
            'woo_consumer_key' => 'required|string',
            'woo_consumer_secret' => 'required|string',
            'status' => 'nullable|boolean'
        ]);

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
        $request->validate([
            'woo_store_url' => 'required|url',
            'woo_consumer_key' => 'required|string',
            'woo_consumer_secret' => 'required|string',
        ]);

        $url = rtrim($request->woo_store_url, '/');
        $key = $request->woo_consumer_key;
        $secret = $request->woo_consumer_secret;

        try {
            // Realizar una petición simple al endpoint de system status
            $response = Http::timeout(10)
                ->withBasicAuth($key, $secret)
                ->get($url . '/wp-json/wc/v3/system_status');

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Conexión exitosa con WooCommerce. API responde correctamente.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error de conexión. Respuesta HTTP: ' . $response->status(),
                'details' => $response->json()
            ], 400);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo establecer conexión con el servidor. Valida la URL y que no haya restricciones de firewall.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
