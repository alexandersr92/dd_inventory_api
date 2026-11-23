<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Tag;
use App\Exports\ProductBulkTemplateExport;
use App\Services\ExcelProductParser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ProductBulkController extends Controller
{
    use AuthorizesRequests;

    public function template()
    {
        $this->authorize('update', Product::class);
        $orgId = Auth::user()->organization_id;

        return Excel::download(
            new ProductBulkTemplateExport($orgId),
            'plantilla_actualizacion_productos.xlsx'
        );
    }

    public function preview(Request $request)
    {
        $this->authorize('update', Product::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $orgId = Auth::user()->organization_id;

        $parsed = ExcelProductParser::parseRows($file);
        $rows = $parsed['rows'];

        $items = [];
        $invalid = [];

        foreach ($rows as $item) {
            $rowIndex = $item['row_index'];
            $data = $item['data'];

            $sku = trim((string)($data['sku'] ?? ''));
            $newSku = trim((string)($data['new_sku'] ?? ''));
            $rawCategory = trim((string)($data['category'] ?? ''));
            $rawBrand = trim((string)($data['brand'] ?? ''));
            $name = trim((string)($data['name'] ?? ''));
            $barcode = trim((string)($data['barcode'] ?? ''));
            $rawTags = trim((string)($data['tags'] ?? ''));

            $errors = [];
            $fieldErrors = [];

            if ($sku === '') {
                $errors[] = "Fila {$rowIndex}: La columna 'Código' es obligatoria.";
                $fieldErrors['sku'] = "Código (SKU) requerido.";
            }

            $product = $sku !== ''
                ? Product::where('organization_id', $orgId)->where('sku', $sku)->first()
                : null;

            if ($sku !== '' && !$product) {
                $errors[] = "Fila {$rowIndex}: El producto con código '{$sku}' no existe en el catálogo.";
                $fieldErrors['sku'] = "Producto no encontrado.";
            }

            // Category resolution
            $resolvedCategory = null;
            if ($rawCategory !== '') {
                $resolvedCategory = Category::resolveByPathOrName($rawCategory, $orgId);
                if (!$resolvedCategory) {
                    $errors[] = "Fila {$rowIndex}: Categoría '{$rawCategory}' no encontrada.";
                    $fieldErrors['category'] = "Categoría inexistente.";
                }
            }

            // Brand resolution
            $resolvedBrand = null;
            if ($rawBrand !== '') {
                $resolvedBrand = Brand::resolveByName($rawBrand, $orgId);
                if (!$resolvedBrand) {
                    $errors[] = "Fila {$rowIndex}: Marca '{$rawBrand}' no encontrada.";
                    $fieldErrors['brand'] = "Marca inexistente.";
                }
            }

            // Barcode uniqueness check if changed
            if ($product && $barcode !== '' && $barcode !== $product->barcode) {
                $barcodeExists = Product::where('organization_id', $orgId)
                    ->where('id', '!=', $product->id)
                    ->where('barcode', $barcode)
                    ->exists();

                if ($barcodeExists) {
                    $errors[] = "Fila {$rowIndex}: El código de barras '{$barcode}' ya está en uso por otro producto.";
                    $fieldErrors['barcode'] = "Código de barras duplicado.";
                }
            }

            // New SKU uniqueness check if changed
            if ($product && $newSku !== '' && $newSku !== $product->sku) {
                $skuExists = Product::where('organization_id', $orgId)
                    ->where('id', '!=', $product->id)
                    ->where('sku', $newSku)
                    ->exists();

                if ($skuExists) {
                    $errors[] = "Fila {$rowIndex}: El código nuevo '{$newSku}' ya está en uso por otro producto.";
                    $fieldErrors['new_sku'] = "Código nuevo duplicado.";
                }
            }

            if (!empty($errors)) {
                $invalid[] = [
                    'row' => $rowIndex,
                    'sku' => $sku,
                    'errors' => $errors,
                    'field_errors' => $fieldErrors,
                    'data' => [
                        'sku' => $sku,
                        'new_sku' => $newSku,
                        'name' => $name,
                        'category' => $rawCategory,
                        'brand' => $rawBrand,
                        'barcode' => $barcode,
                        'tags' => $rawTags,
                    ],
                ];
                continue;
            }

            // Compute changes
            $nameChanged = ($name !== '' && $name !== $product->name);
            $barcodeChanged = ($barcode !== '' && $barcode !== $product->barcode);
            $skuChanged = ($newSku !== '' && $newSku !== $product->sku);
            $catChanged = ($resolvedCategory && $resolvedCategory->id !== $product->category_id);
            $brandChanged = ($resolvedBrand && $resolvedBrand->id !== $product->brand_id);

            $hasChanges = $nameChanged || $barcodeChanged || $skuChanged || $catChanged || $brandChanged || ($rawTags !== '');

            $items[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'new_sku' => [
                    'current' => $product->sku,
                    'new' => $newSku !== '' ? $newSku : $product->sku,
                    'changed' => $skuChanged,
                ],
                'name' => [
                    'current' => $product->name,
                    'new' => $name !== '' ? $name : $product->name,
                    'changed' => $nameChanged,
                ],
                'barcode' => [
                    'current' => $product->barcode,
                    'new' => $barcode !== '' ? $barcode : $product->barcode,
                    'changed' => $barcodeChanged,
                ],
                'category_id' => [
                    'current' => $product->category_id,
                    'new' => $resolvedCategory ? $resolvedCategory->id : $product->category_id,
                    'changed' => $catChanged,
                ],
                'category_name' => [
                    'current' => $product->category ? $product->category->full_path : null,
                    'new' => $resolvedCategory ? $resolvedCategory->full_path : ($product->category ? $product->category->full_path : null),
                    'changed' => $catChanged,
                ],
                'brand_id' => [
                    'current' => $product->brand_id,
                    'new' => $resolvedBrand ? $resolvedBrand->id : $product->brand_id,
                    'changed' => $brandChanged,
                ],
                'brand_name' => [
                    'current' => $product->brand ? $product->brand->name : null,
                    'new' => $resolvedBrand ? $resolvedBrand->name : ($product->brand ? $product->brand->name : null),
                    'changed' => $brandChanged,
                ],
                'tags' => [
                    'current' => $product->tags->pluck('name')->toArray(),
                    'new' => $rawTags !== '' ? array_map('trim', explode(',', $rawTags)) : $product->tags->pluck('name')->toArray(),
                    'changed' => $rawTags !== '',
                ],
                'has_changes' => $hasChanges,
            ];
        }

        return response()->json([
            'total_rows' => count($rows),
            'valid_count' => count($items),
            'changed_count' => count(array_filter($items, fn($i) => $i['has_changes'])),
            'invalid_count' => count($invalid),
            'items' => $items,
            'invalid' => $invalid,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('update', Product::class);
        $orgId = Auth::user()->organization_id;

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|uuid',
            'items.*.name' => 'nullable|string',
            'items.*.barcode' => 'nullable|string',
            'items.*.category_id' => 'nullable|uuid',
            'items.*.brand_id' => 'nullable|uuid',
            'items.*.tags' => 'nullable|array',
        ]);

        $items = $request->items;
        $updatedCount = 0;
        $notFoundCount = 0;
        $errors = [];

        DB::transaction(function () use ($items, $orgId, &$updatedCount, &$notFoundCount, &$errors) {
            foreach ($items as $item) {
                $product = Product::where('organization_id', $orgId)->find($item['product_id']);
                if (!$product) {
                    $notFoundCount++;
                    continue;
                }

                // Check new_sku uniqueness if updating
                if (isset($item['new_sku']) && trim($item['new_sku']) !== '' && trim($item['new_sku']) !== $product->sku) {
                    $newSkuVal = trim($item['new_sku']);
                    $exists = Product::where('organization_id', $orgId)
                        ->where('id', '!=', $product->id)
                        ->where('sku', $newSkuVal)
                        ->exists();

                    if ($exists) {
                        $errors[] = "Código nuevo '{$newSkuVal}' ya está en uso.";
                        continue;
                    }
                    $product->sku = $newSkuVal;
                }

                // Check barcode uniqueness if updating
                if (isset($item['barcode']) && $item['barcode'] !== '' && $item['barcode'] !== $product->barcode) {
                    $exists = Product::where('organization_id', $orgId)
                        ->where('id', '!=', $product->id)
                        ->where('barcode', $item['barcode'])
                        ->exists();

                    if ($exists) {
                        $errors[] = "Código de barras '{$item['barcode']}' ya está en uso.";
                        continue;
                    }
                    $product->barcode = $item['barcode'];
                }

                if (isset($item['name']) && trim($item['name']) !== '') {
                    $product->name = trim($item['name']);
                }

                if (array_key_exists('category_id', $item)) {
                    $product->category_id = $item['category_id'] ?: null;
                    if ($product->category_id) {
                        $product->categories()->sync([$product->category_id]);
                    } else {
                        $product->categories()->sync([]);
                    }
                }

                if (array_key_exists('brand_id', $item)) {
                    $product->brand_id = $item['brand_id'] ?: null;
                }

                if (isset($item['tags']) && is_array($item['tags'])) {
                    $tagIds = [];
                    foreach ($item['tags'] as $tagName) {
                        $trimmedTag = trim($tagName);
                        if ($trimmedTag === '') continue;

                        $tag = Tag::where('organization_id', $orgId)
                            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($trimmedTag)])
                            ->first();

                        if (!$tag) {
                            $tag = Tag::create([
                                'organization_id' => $orgId,
                                'name' => $trimmedTag,
                            ]);
                        }
                        $tagIds[] = $tag->id;
                    }
                    $product->tags()->sync($tagIds);
                }

                // Strictly save descriptive fields without altering quantity, inventory, movements or price
                $product->save();
                $updatedCount++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Se actualizaron {$updatedCount} productos correctamente.",
            'updated_count' => $updatedCount,
            'not_found_count' => $notFoundCount,
            'errors_count' => count($errors),
            'errors' => $errors,
        ]);
    }
}
