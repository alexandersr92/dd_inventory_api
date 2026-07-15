<?php

namespace App\Jobs;

use App\Models\WooCommerceIntegration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncStockToWooCommerce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected WooCommerceIntegration $integration;
    protected string $sku;
    protected float $quantity;

    /**
     * Create a new job instance.
     */
    public function __construct(WooCommerceIntegration $integration, string $sku, float $quantity)
    {
        $this->integration = $integration;
        $this->sku = $sku;
        $this->quantity = $quantity;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = $this->integration->woo_store_url;
        $key = $this->integration->woo_consumer_key;
        $secret = $this->integration->woo_consumer_secret;
        $sku = $this->sku;
        $qty = (int) $this->quantity;

        if (empty($sku)) {
            return;
        }

        try {
            // 1. Buscar el producto por SKU en WooCommerce
            $searchResponse = Http::timeout(15)
                ->withBasicAuth($key, $secret)
                ->get($url . '/wp-json/wc/v3/products', [
                    'sku' => $sku,
                    'per_page' => 1
                ]);

            if (!$searchResponse->successful()) {
                Log::error("SyncStockToWooCommerce: Error buscando producto con SKU {$sku} en WooCommerce. Status: " . $searchResponse->status());
                return;
            }

            $products = $searchResponse->json();

            if (empty($products)) {
                Log::warning("SyncStockToWooCommerce: Producto con SKU {$sku} no encontrado en WooCommerce.");
                return;
            }

            $wooProduct = $products[0];
            $wooProductId = $wooProduct['id'];

            // 2. Actualizar el stock en WooCommerce
            $updateResponse = Http::timeout(15)
                ->withBasicAuth($key, $secret)
                ->put($url . '/wp-json/wc/v3/products/' . $wooProductId, [
                    'manage_stock' => true,
                    'stock_quantity' => $qty
                ]);

            if (!$updateResponse->successful()) {
                Log::error("SyncStockToWooCommerce: Error actualizando stock del producto ID {$wooProductId} (SKU: {$sku}) en WooCommerce. Status: " . $updateResponse->status());
            } else {
                Log::info("SyncStockToWooCommerce: Stock sincronizado correctamente en WooCommerce para SKU {$sku}. Nueva cantidad: {$qty}");
            }
        } catch (\Throwable $e) {
            Log::error("SyncStockToWooCommerce: Excepción al sincronizar stock para SKU {$sku}. Error: " . $e->getMessage());
        }
    }
}
