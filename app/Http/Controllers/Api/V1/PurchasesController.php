<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchases;
use App\Models\PurchaseDetail;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Product;
use App\Services\InventoryMovementService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ChunkImport;
use Illuminate\Support\Collection; 
use App\Http\Requests\StorePurchaseRequest;

use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\PurchaseResource;
use App\Http\Resources\PurchaseCollection;


class PurchasesController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Purchases::class);
        $orgId = Auth::user()->organization_id;
        $storeId = $request->query('store_id');
        
        $query = Purchases::where('organization_id', $orgId);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $sortBy = $request->query('sort_by') ?? $request->query('sort');
        $order = $request->query('order') ?? $request->query('direction');

        $allowedSortFields = [
            'id', 'total', 'purchase_date', 'total_items', 'status', 
            'created_at', 'updated_at', 'store_id', 'supplier_id', 'inventory_id'
        ];

        if ($sortBy && in_array($sortBy, $allowedSortFields)) {
            $order = in_array(strtolower($order), ['asc', 'desc']) ? $order : 'asc';
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $purchases = $query->get();

        return response(
            new PurchaseCollection($purchases),
            Response::HTTP_CREATED
        );
    }
   

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseRequest $request)
    {
        $this->authorize('create', Purchases::class);
        $purchase = app(\App\Services\PurchaseReceiptService::class)->save($request->all());
        return response(new PurchaseResource($purchase), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Purchases $purchase)
    {
        $this->authorize('view', $purchase);
        return response(
            new PurchaseResource($purchase),
            Response::HTTP_OK
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchases $purchase)
    {
        $this->authorize('update', $purchase);
        $purchase = app(\App\Services\PurchaseReceiptService::class)->save($request->all(), $purchase);
        return response(new PurchaseResource($purchase), Response::HTTP_OK);
    }

    public function destroy(Purchases $purchase)
    {
        $this->authorize('delete', $purchase);
        app(\App\Services\PurchaseReceiptService::class)->cancel($purchase);
        return response()->json(['message' => 'Purchase deleted successfully.']);
    }

    public function upload(Request $request)
    {
        $this->authorize('create', Purchases::class);
     
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $orgId = Auth::user()->organization_id;

        // Arrays para los resultados
        $validExisting = [];
        $validNew = [];
        $invalid = [];

        // Leer archivo en chunks para evitar problemas de memoria
        Excel::import(new class($validExisting, $validNew, $invalid, $orgId) implements \Maatwebsite\Excel\Concerns\ToCollection {
            private $validExisting;
            private $validNew;
            private $invalid;
            private $orgId;

            public function __construct(&$validExisting, &$validNew, &$invalid, $orgId)
            {
                $this->validExisting = &$validExisting;
                $this->validNew = &$validNew;
                $this->invalid = &$invalid;
                $this->orgId = $orgId;
            }

            public function collection(Collection $rows)
            {
                foreach ($rows->skip(1) as $index => $row) { // Saltar encabezados
                    $sku = trim((string) ($row[0] ?? ''));
                    $price = trim((string) ($row[5] ?? ''));
                    $quantity = trim((string) ($row[6] ?? ''));
                    $cost = trim((string) ($row[7] ?? ''));

                    $usageInput = mb_strtolower(trim((string) ($row[8] ?? '')));
                    $usageInput = ['venta' => 'sale', 'insumo' => 'supply', 'ambos' => 'both'][$usageInput] ?? $usageInput;
                    $errors = [];
                    if ($usageInput !== '' && !in_array($usageInput, ['sale', 'supply', 'both'], true)) $errors[] = 'La clasificación debe ser Venta, Insumo o Ambos';

                    //if all fields are empty, skip
                    if (empty($sku) && empty($price) && empty($quantity) && empty($cost)) {
                        continue;
                    }

                    // Validaciones
                    if (empty($sku)) {
                        $errors[] = 'El SKU está vacío';
                    }

                    $cleanPrice = str_replace(['C$', '$', ' '], '', $price);
                    if ($cleanPrice === '' || !is_numeric($cleanPrice)) {
                        $errors[] = 'El precio no es válido';
                    }

                    if ($quantity === '' || !is_numeric($quantity)) {
                        $errors[] = 'La cantidad no es válida';
                    }

                    $cleanCost = str_replace(['C$', '$', ' '], '', $cost);
                    if ($cleanCost === '' || !is_numeric($cleanCost)) {
                        $errors[] = 'El costo no es válido';
                    }

                    // Verificar si existe el SKU en la base de datos para esta organización
                    $product = Product::where('organization_id', $this->orgId)->where('sku', $sku)->first();

                    if (empty($errors)) {
                        // Datos válidos
                        if ($product) {
                            $this->validExisting[] = [
                                'product_id' => $product->id,
                                'sku' => $sku,
                                'product_name' => trim((string) ($row[3] ?? '')),
                                'barcode' => trim((string) ($row[4] ?? '')),
                                'quantity' => (float)$quantity,
                                'product_usage' => $usageInput ?: ($product?->product_usage ?? 'sale'),
                                'material_profile' => $product?->material_profile ?? null,
                                'unit_of_measure' => $product?->unit_of_measure ?? 'unit',
                                'price' => (float)$cleanPrice,
                                'cost' => (float)$cleanCost,
                            ];
                        } else {
                            $this->validNew[] = [
                                'product_id' => null,
                                'sku' => $sku,
                                'product_name' => trim((string) ($row[3] ?? '')),
                                'barcode' => trim((string) ($row[4] ?? '')),
                                'product_usage' => $usageInput ?: ($product?->product_usage ?? 'sale'),
                                'material_profile' => $product?->material_profile ?? null,
                                'unit_of_measure' => $product?->unit_of_measure ?? 'unit',
                                'price' => (float)$cleanPrice,
                                'quantity' => (float)$quantity,
                                'cost' => (float)$cleanCost,
                            ];
                        }
                    } else {
                        // Datos inválidos
                        $this->invalid[] = [
                            'row' => $index + 2, // Fila actual (sumar 2 porque se salta encabezado)
                            'errors' => $errors,
                            'data' => [
                                'sku' => $sku,
                                'product_name' => trim((string) ($row[3] ?? '')),
                                'barcode' => trim((string) ($row[4] ?? '')),
                                'price' => $price,
                                'quantity' => $quantity,
                                'cost' => $cost,
                            ],
                        ];
                    }
                }
            }
        }, $file);

        return response()->json([
            'valid_existing' => $validExisting,
            'valid_new' => $validNew,
            'invalid' => $invalid,
        ]);
    }
}

