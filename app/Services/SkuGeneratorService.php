<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductSkuSetting;
use App\Models\SkuRegenerationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkuGeneratorService
{
    /**
     * Valida la sintaxis y componentes de la plantilla de SKU.
     */
    public static function validatePattern(string $pattern): array
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return [
                'valid' => false,
                'error' => 'La plantilla de SKU no puede estar vacía.',
            ];
        }

        // Exigir componente {consecutivo:N} donde N sea entre 1 y 12
        if (!preg_match('/\{consecutivo:([1-9]|1[0-2])\}/', $pattern)) {
            return [
                'valid' => false,
                'error' => 'La plantilla debe incluir obligatoriamente el componente {consecutivo:N} (por ejemplo: {consecutivo:5}).',
            ];
        }

        // Verificar componentes encerrados en llaves
        preg_match_all('/\{([^}]+)\}/', $pattern, $matches);
        $allowed = ['categoria', 'marca'];

        foreach ($matches[1] as $token) {
            if (in_array(strtolower($token), $allowed)) {
                continue;
            }
            if (preg_match('/^consecutivo:([1-9]|1[0-2])$/', strtolower($token))) {
                continue;
            }
            return [
                'valid' => false,
                'error' => "El componente '{{$token}}' no es válido. Los componentes admitidos son {categoria}, {marca} y {consecutivo:N}.",
            ];
        }

        // Verificar que no haya llaves desbalanceadas
        $openBraces = substr_count($pattern, '{');
        $closeBraces = substr_count($pattern, '}');
        if ($openBraces !== $closeBraces) {
            return [
                'valid' => false,
                'error' => 'La plantilla contiene llaves desbalanceadas.',
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Limpia y extrae el token para categoría (ej. 'CAM' para 'Camisas').
     */
    public static function cleanCategoryToken(?string $name): string
    {
        if (!$name) {
            return '';
        }

        // Si es una ruta como 'Ropa > Camisas', tomar la categoría hoja
        if (str_contains($name, '>')) {
            $parts = explode('>', $name);
            $name = trim(end($parts));
        }

        $clean = Str::ascii($name);
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $clean);
        $clean = strtoupper($clean);

        return substr($clean, 0, 3);
    }

    /**
     * Limpia y extrae el token para marca (ej. 'NIK' para 'Nike').
     */
    public static function cleanBrandToken(?string $name): string
    {
        if (!$name) {
            return '';
        }

        $clean = Str::ascii($name);
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $clean);
        $clean = strtoupper($clean);

        return substr($clean, 0, 3);
    }

    /**
     * Renderiza una plantilla con los tokens provistos.
     */
    public static function renderPattern(string $pattern, string $categoryToken, string $brandToken, string $formattedConsecutive): string
    {
        $rendered = str_ireplace('{categoria}', $categoryToken, $pattern);
        $rendered = str_ireplace('{marca}', $brandToken, $rendered);
        $rendered = preg_replace('/\{consecutivo:\d+\}/i', $formattedConsecutive, $rendered);

        return $rendered;
    }

    /**
     * Obtiene una vista previa de la plantilla para presentación visual.
     */
    public static function getPreview(string $pattern, int $counter = 42, ?string $categoryName = 'Camisas', ?string $brandName = 'Nike'): string
    {
        preg_match('/\{consecutivo:(\d+)\}/i', $pattern, $match);
        $digits = isset($match[1]) ? (int)$match[1] : 5;

        $catToken = self::cleanCategoryToken($categoryName) ?: 'CAM';
        $brandToken = self::cleanBrandToken($brandName) ?: 'NIK';
        $formattedConsecutive = str_pad((string)$counter, $digits, '0', STR_PAD_LEFT);

        return self::renderPattern($pattern, $catToken, $brandToken, $formattedConsecutive);
    }

    /**
     * Obtiene o crea la configuración de SKU para la organización.
     */
    public static function getOrCreateConfig(string $orgId): ProductSkuSetting
    {
        return ProductSkuSetting::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'enabled' => false,
                'pattern' => 'PROD-{consecutivo:5}',
                'counter' => 0,
            ]
        );
    }

    /**
     * Genera automáticamente un SKU para un nuevo producto con protección concurrente y evitando colisiones.
     */
    public static function generateForProduct(string $orgId, ?string $categoryId = null, ?string $brandId = null): ?string
    {
        return DB::transaction(function () use ($orgId, $categoryId, $brandId) {
            $config = ProductSkuSetting::where('organization_id', $orgId)->lockForUpdate()->first();

            if (!$config) {
                $config = ProductSkuSetting::create([
                    'organization_id' => $orgId,
                    'enabled' => false,
                    'pattern' => 'PROD-{consecutivo:5}',
                    'counter' => 0,
                ]);
            }

            if (!$config->enabled) {
                return null;
            }

            $pattern = $config->pattern;
            $needsCategory = (stripos($pattern, '{categoria}') !== false);
            $needsBrand = (stripos($pattern, '{marca}') !== false);

            $categoryToken = '';
            if ($needsCategory) {
                if (!$categoryId) {
                    throw ValidationException::withMessages([
                        'category_id' => 'La categoría es obligatoria para generar el SKU automático según la plantilla configurada.',
                    ]);
                }
                $cat = Category::where('organization_id', $orgId)->find($categoryId);
                $categoryToken = self::cleanCategoryToken($cat?->name ?: '');
                if ($categoryToken === '') {
                    $categoryToken = 'GEN';
                }
            }

            $brandToken = '';
            if ($needsBrand) {
                if (!$brandId) {
                    throw ValidationException::withMessages([
                        'brand_id' => 'La marca es obligatoria para generar el SKU automático según la plantilla configurada.',
                    ]);
                }
                $brand = Brand::where('organization_id', $orgId)->find($brandId);
                $brandToken = self::cleanBrandToken($brand?->name ?: '');
                if ($brandToken === '') {
                    $brandToken = 'GEN';
                }
            }

            preg_match('/\{consecutivo:(\d+)\}/i', $pattern, $match);
            $digits = isset($match[1]) ? (int)$match[1] : 5;

            // Búsqueda de consecutivo disponible sin colisión con códigos manuales o existentes
            do {
                $config->counter++;
                $formattedConsecutive = str_pad((string)$config->counter, $digits, '0', STR_PAD_LEFT);
                $candidateSku = self::renderPattern($pattern, $categoryToken, $brandToken, $formattedConsecutive);

                $exists = Product::where('organization_id', $orgId)
                    ->where('sku', $candidateSku)
                    ->exists();
            } while ($exists);

            $config->save();

            return $candidateSku;
        });
    }

    /**
     * Genera la vista previa de regeneración de todo el catálogo.
     */
    public static function previewRegeneration(string $orgId, ?string $patternOverride = null): array
    {
        $config = self::getOrCreateConfig($orgId);
        $pattern = $patternOverride ?: $config->pattern;

        $validation = self::validatePattern($pattern);
        if (!$validation['valid']) {
            throw ValidationException::withMessages([
                'pattern' => $validation['error'],
            ]);
        }

        $needsCategory = (stripos($pattern, '{categoria}') !== false);
        $needsBrand = (stripos($pattern, '{marca}') !== false);

        $products = Product::where('organization_id', $orgId)
            ->with(['category', 'brand'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $missingData = [];
        foreach ($products as $p) {
            $missingFields = [];
            if ($needsCategory && empty($p->category_id)) {
                $missingFields[] = 'categoria';
            }
            if ($needsBrand && empty($p->brand_id)) {
                $missingFields[] = 'marca';
            }

            if (!empty($missingFields)) {
                $missingData[] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'missing_fields' => $missingFields,
                ];
            }
        }

        if (!empty($missingData)) {
            return [
                'can_proceed' => false,
                'total_products' => $products->count(),
                'preview_token' => '',
                'pattern' => $pattern,
                'items' => [],
                'missing_data' => $missingData,
                'message' => 'Existen productos sin categoría o marca requerida por la plantilla. Se debe asignarles el dato antes de regenerar los SKU.',
            ];
        }

        preg_match('/\{consecutivo:(\d+)\}/i', $pattern, $match);
        $digits = isset($match[1]) ? (int)$match[1] : 5;

        $items = [];
        $seq = 1;

        foreach ($products as $p) {
            $catToken = $needsCategory ? (self::cleanCategoryToken($p->category?->name) ?: 'GEN') : '';
            $brandToken = $needsBrand ? (self::cleanBrandToken($p->brand?->name) ?: 'GEN') : '';
            $formattedConsecutive = str_pad((string)$seq, $digits, '0', STR_PAD_LEFT);
            $proposedSku = self::renderPattern($pattern, $catToken, $brandToken, $formattedConsecutive);

            $items[] = [
                'id' => $p->id,
                'name' => $p->name,
                'current_sku' => $p->sku,
                'proposed_sku' => $proposedSku,
                'category_name' => $p->category?->name,
                'brand_name' => $p->brand?->name,
            ];

            $seq++;
        }

        // Construir token firmado con checksum de estado actual para prevenir vistas previas obsoletas
        $productsChecksum = md5($products->pluck('updated_at', 'id')->toJson());
        $tokenData = [
            'org_id' => $orgId,
            'pattern' => $pattern,
            'count' => $products->count(),
            'checksum' => $productsChecksum,
            'counter' => $config->counter,
            'timestamp' => now()->timestamp,
        ];

        $previewToken = encrypt($tokenData);

        return [
            'can_proceed' => true,
            'total_products' => $products->count(),
            'preview_token' => $previewToken,
            'pattern' => $pattern,
            'items' => $items,
            'missing_data' => [],
            'message' => 'Vista previa generada correctamente.',
        ];
    }

    /**
     * Aplica la regeneración de SKU en una transacción atómica con bloqueo y auditoría.
     */
    public static function confirmRegeneration(string $orgId, string $previewToken, ?string $userId = null): array
    {
        try {
            $tokenData = decrypt($previewToken);
        } catch (\Throwable $e) {
            abort(409, 'La vista previa es inválida o ha expirado. Por favor genera una nueva vista previa.');
        }

        if (!is_array($tokenData) || ($tokenData['org_id'] ?? null) !== $orgId) {
            abort(403, 'Acceso denegado a la vista previa.');
        }

        // Verificar expiración (30 minutos)
        if (now()->timestamp - ($tokenData['timestamp'] ?? 0) > 1800) {
            abort(409, 'La vista previa ha expirado. Por favor genera una nueva revisión antes de aplicar los códigos.');
        }

        return DB::transaction(function () use ($orgId, $tokenData, $userId) {
            $config = ProductSkuSetting::where('organization_id', $orgId)->lockForUpdate()->first();
            if (!$config) {
                $config = self::getOrCreateConfig($orgId);
            }

            $pattern = $tokenData['pattern'];

            // Comprobar si el consecutivo o el catálogo cambiaron desde la vista previa
            $products = Product::where('organization_id', $orgId)
                ->with(['category', 'brand'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $currentChecksum = md5($products->pluck('updated_at', 'id')->toJson());

            if (
                $config->counter !== $tokenData['counter'] ||
                $products->count() !== $tokenData['count'] ||
                $currentChecksum !== $tokenData['checksum']
            ) {
                abort(409, 'El catálogo, la plantilla o el consecutivo han cambiado desde la última vista previa. Por favor realiza una nueva revisión antes de aplicar los códigos.');
            }

            $needsCategory = (stripos($pattern, '{categoria}') !== false);
            $needsBrand = (stripos($pattern, '{marca}') !== false);

            preg_match('/\{consecutivo:(\d+)\}/i', $pattern, $match);
            $digits = isset($match[1]) ? (int)$match[1] : 5;

            $skuMap = [];
            $seq = 1;

            // Fase 1: Asignar SKU temporales únicos para evitar colisiones entre códigos existentes
            foreach ($products as $p) {
                Product::where('id', $p->id)->update([
                    'sku' => '__TMP_SKU_' . $p->id,
                ]);
            }

            // Fase 2: Asignar el SKU propuesto final y registrar el mapa
            foreach ($products as $p) {
                $catToken = $needsCategory ? (self::cleanCategoryToken($p->category?->name) ?: 'GEN') : '';
                $brandToken = $needsBrand ? (self::cleanBrandToken($p->brand?->name) ?: 'GEN') : '';
                $formattedConsecutive = str_pad((string)$seq, $digits, '0', STR_PAD_LEFT);
                $newSku = self::renderPattern($pattern, $catToken, $brandToken, $formattedConsecutive);

                Product::where('id', $p->id)->update([
                    'sku' => $newSku,
                ]);

                $skuMap[] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'old_sku' => $p->sku,
                    'new_sku' => $newSku,
                ];

                $seq++;
            }

            // Actualizar consecutivo al máximo asignado
            $config->counter = $products->count();
            $config->pattern = $pattern;
            $config->save();

            // Guardar registro de auditoría
            $log = SkuRegenerationLog::create([
                'organization_id' => $orgId,
                'user_id' => $userId,
                'pattern' => $pattern,
                'total_products' => $products->count(),
                'sku_map' => $skuMap,
            ]);

            return [
                'success' => true,
                'message' => "Se regeneraron exitosamente los SKU de {$products->count()} productos.",
                'total_updated' => $products->count(),
                'pattern' => $pattern,
                'executed_at' => $log->created_at->toISOString(),
                'executed_by' => $userId,
                'sku_map' => $skuMap,
            ];
        });
    }
}
