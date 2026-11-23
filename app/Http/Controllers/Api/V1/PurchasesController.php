<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchases;
use App\Models\PurchaseDetail;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Supplier;
use App\Services\InventoryMovementService;
use App\Services\ExcelProductParser;
use App\Exports\PurchasesTemplateExport;

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
        //

        $orgId = Auth::user()->organization_id;
        $userID = Auth::user()->id;


        $purchase = new Purchases();
        $purchase->user_id = $userID;
        $purchase->organization_id = $orgId;
        $purchase->store_id = $request->store_id;
        $purchase->supplier_id = $request->supplier_id;
        $purchase->inventory_id = $request->inventory_id;
        $purchase->total = $request->total;
        $purchase->purchase_date = $request->purchase_date;
        $purchase->invoice_number = $request->invoice_number;
        $purchase->purchase_note = $request->purchase_note;
        $purchase->total_items = $request->total_items;
        $purchase->save();
    
        $products = $request->products;
   

     
        foreach ($products as $product) {
            //if product id is null, create a new product

            //find product if exists by sku or barcode
            $productExists = Product::where('organization_id', $orgId)
                ->where(function ($query) use ($product) {
                    $query->where('sku', $product['sku']);
                    if (!empty($product['barcode'])) {
                        $query->orWhere('barcode', $product['barcode']);
                    }
                })->first();
            if($productExists){
                $product['product_id'] = $productExists->id;
            }

            if($product['product_id'] === null){
                $newProduct = new Product();
                $newProduct->organization_id = $orgId;
                if (empty($product['sku']) || str_starts_with($product['sku'], '[Auto')) {
                    $autoSku = \App\Services\SkuGeneratorService::generateForProduct(
                        $orgId,
                        $product['category_id'] ?? null,
                        $product['brand_id'] ?? null
                    );
                    $newProduct->sku = $autoSku ?: ($product['sku'] ?? '');
                } else {
                    $newProduct->sku = $product['sku'];
                }
                $newProduct->name = $product['product_name'] ?? ($product['name'] ?? '');
                $newProduct->barcode = $product['barcode'] ?? '';
                $newProduct->price = $product['price'];
                $newProduct->cost = $product['cost'];
                $newProduct->min_stock = 0;
                $newProduct->unit_of_measure = 'unit';
                $newProduct->status = 'active';
                $newProduct->category_id = $product['category_id'] ?? null;
                $newProduct->brand_id = $product['brand_id'] ?? null;
                $newProduct->save();

                if ($newProduct->category_id) {
                    $newProduct->categories()->sync([$newProduct->category_id]);
                }

                $product['product_id'] = $newProduct->id;
            }
            
            $purchaseDetail = new PurchaseDetail();
            $purchaseDetail->purchase_id = $purchase->id;
            $purchaseDetail->product_id = $product['product_id'];
            $purchaseDetail->quantity = $product['quantity'];
            $purchaseDetail->price = $product['price'];
            $purchaseDetail->cost = $product['cost'];
            $purchaseDetail->save();

        
        }
       
    

        //crear inventario o subir inventario

        $inventory = Inventory::where('organization_id', $orgId)->where('id', $request->inventory_id)->first();

        if($inventory){
            $listProducts = PurchaseDetail::where('purchase_id', $purchase->id)->get();
            $movementService = app(InventoryMovementService::class);

            foreach ($listProducts as $product) {
                //get inventoryDetail
                $inventoryDetail = InventoryDetail::where('inventory_id', $inventory->id)->where('product_id', $product['product_id'])->first();
                if(!$inventoryDetail){
                    $inventoryDetail = InventoryDetail::create([
                        'inventory_id' => $inventory->id,
                        'product_id' => $product['product_id'],
                        'quantity' => 0,
                        'price' => $product['price'],
                        'status' => 'active'
                    ]);
                }

                $movementService->recordMovement([
                    'inventory_detail_id' => $inventoryDetail->id,
                    'type' => 'purchase',
                    'quantity' => (float) $product['quantity'],
                    'reason' => "Ingreso por Compra ID: {$purchase->id}",
                    'user_id' => Auth::id(),
                    'reference_id' => $purchase->id,
                    'reference_type' => Purchases::class,
                ]);
            }
        }
        //responder con los resultados
        return response(
            new PurchaseResource($purchase),
            Response::HTTP_CREATED
        );
        


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
        $validated = $request->validate([
            'invoice_number' => 'sometimes|nullable|string|max:255',
        ]);
        $orgId = Auth::user()->organization_id;
        //obtener la compra actual 
        $inventoryID = $purchase->inventory_id;
        $requestInventoryID = $request->inventory_id;
        $purchaseDetails = PurchaseDetail::where('purchase_id', $purchase->id)->get();
        $newProductList = $request->products;
        $newTotalItems = 0;

        $movementService = app(InventoryMovementService::class);
        foreach ($purchaseDetails as $purchaseDetail) {
            $inventoryDetail = InventoryDetail::where('inventory_id', $inventoryID)->where('product_id', $purchaseDetail->product_id)->first();
            if ($inventoryDetail) {
                $movementService->recordMovement([
                    'inventory_detail_id' => $inventoryDetail->id,
                    'type' => 'purchase_cancel',
                    'quantity' => (float) $purchaseDetail->quantity,
                    'reason' => "Reversión de compra previa por modificación de Compra ID: {$purchase->id}",
                    'user_id' => Auth::id(),
                    'reference_id' => $purchase->id,
                    'reference_type' => Purchases::class,
                ]);
            }
        }
    
        //clear purchase details
        PurchaseDetail::where('purchase_id', $purchase->id)->delete();

        //add new purchase details if no exist any product create it
        foreach ($newProductList as $product) {
            //if product id is null, create a new product
            if($product['product_id'] == null){
                $newProduct = new Product();
                $newProduct->organization_id = $orgId;
                if (empty($product['sku']) || str_starts_with($product['sku'], '[Auto')) {
                    $autoSku = \App\Services\SkuGeneratorService::generateForProduct(
                        $orgId,
                        $product['category_id'] ?? null,
                        $product['brand_id'] ?? null
                    );
                    $newProduct->sku = $autoSku ?: ($product['sku'] ?? '');
                } else {
                    $newProduct->sku = $product['sku'];
                }
                $newProduct->name = $product['product_name'];
                $newProduct->barcode = $product['barcode'] ?? '';
                $newProduct->price = $product['price'];
                $newProduct->cost = $product['cost'];
                $newProduct->min_stock = 0;
                $newProduct->unit_of_measure = 'unit';
                $newProduct->status = 'active';
                $newProduct->save();
                $product['product_id'] = $newProduct->id;
            }
            $purchaseDetail = new PurchaseDetail();
            $purchaseDetail->purchase_id = $purchase->id;
            $purchaseDetail->product_id = $product['product_id'];
            $purchaseDetail->quantity = $product['quantity'];
            $purchaseDetail->price = $product['price'];
            $purchaseDetail->cost = $product['cost'];
            $purchaseDetail->save();
            $newTotalItems = $newTotalItems + $product['quantity'];


        }

        //update purchase header
        if (array_key_exists('invoice_number', $validated)) {
            $purchase->invoice_number = $validated['invoice_number'];
        }
        $purchase->store_id = $request->store_id;
        $purchase->supplier_id = $request->supplier_id;
        $purchase->inventory_id = $request->inventory_id;
        $purchase->total = $request->total;
        $purchase->purchase_date = $request->purchase_date;
        $purchase->purchase_note = $request->purchase_note;
        $purchase->total_items = $newTotalItems;
        $purchase->save();

        //update inventory details
        $inventory = Inventory::where('organization_id', $orgId)->where('id', $requestInventoryID)->first();
        if($inventory){
            $listProducts = PurchaseDetail::where('purchase_id', $purchase->id)->get();
            foreach ($listProducts as $product) {
                //get inventoryDetail
                $inventoryDetail = InventoryDetail::where('inventory_id', $inventory->id)->where('product_id', $product['product_id'])->first();
                if(!$inventoryDetail){
                    $inventoryDetail = InventoryDetail::create([
                        'inventory_id' => $inventory->id,
                        'product_id' => $product['product_id'],
                        'quantity' => 0,
                        'price' => $product['price'],
                        'status' => 'active'
                    ]);
                }

                $movementService->recordMovement([
                    'inventory_detail_id' => $inventoryDetail->id,
                    'type' => 'purchase',
                    'quantity' => (float) $product['quantity'],
                    'reason' => "Ingreso por modificación de Compra ID: {$purchase->id}",
                    'user_id' => Auth::id(),
                    'reference_id' => $purchase->id,
                    'reference_type' => Purchases::class,
                ]);
            }
        }


        return response(
            new PurchaseResource($purchase),
            Response::HTTP_OK
        );
   
    }
 

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchases $purchase)
    {
        $this->authorize('delete', $purchase);
        $purchaseID = $purchase->id;
        //find purchase
        $purchase = Purchases::find($purchaseID);
        //change status to anulled

        $purchase->status = 'cancelled';
        $purchase->save();
        //find purchase details and restore inventory
        $purchaseDetails = PurchaseDetail::where('purchase_id', $purchaseID)->get();

        $movementService = app(InventoryMovementService::class);
        foreach ($purchaseDetails as $purchaseDetail) {
            $inventoryDetail = InventoryDetail::where('inventory_id', $purchase->inventory_id)->where('product_id', $purchaseDetail->product_id)->first();
            if ($inventoryDetail) {
                $movementService->recordMovement([
                    'inventory_detail_id' => $inventoryDetail->id,
                    'type' => 'purchase_cancel',
                    'quantity' => (float) $purchaseDetail->quantity,
                    'reason' => "Cancelación de Compra ID: {$purchase->id}",
                    'user_id' => Auth::id(),
                    'reference_id' => $purchase->id,
                    'reference_type' => Purchases::class,
                ]);
            }
        }

        return response()->json(['message' => 'Purchase deleted successfully.']);
    }
    
    public function template()
    {
        $this->authorize('create', Purchases::class);
        $orgId = Auth::user()->organization_id;

        return Excel::download(
            new PurchasesTemplateExport($orgId),
            'plantilla_productos_compras.xlsx'
        );
    }

    public function upload(Request $request)
    {
        $this->authorize('create', Purchases::class);
     
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $orgId = Auth::user()->organization_id;

        $parsed = ExcelProductParser::parseRows($file);
        $rows = $parsed['rows'];

        $validExisting = [];
        $validNew = [];
        $invalid = [];

        $suppliers = Supplier::where('organization_id', $orgId)->get();

        foreach ($rows as $item) {
            $rowIndex = $item['row_index'];
            $data = $item['data'];

            $sku = trim((string)($data['sku'] ?? ''));
            $rawCategory = trim((string)($data['category'] ?? ''));
            $rawBrand = trim((string)($data['brand'] ?? ''));
            $name = trim((string)($data['name'] ?? ''));
            $barcode = trim((string)($data['barcode'] ?? ''));
            $rawPrice = $data['price'] ?? '';
            $rawQuantity = $data['quantity'] ?? '';
            $rawCost = $data['cost'] ?? '';
            $rawTotal = $data['total'] ?? '';
            $rawSupplier = trim((string)($data['supplier'] ?? ''));

            $errors = [];
            $fieldErrors = [];

            // 1. Validar SKU
            if ($sku === '') {
                $skuConfig = \App\Services\SkuGeneratorService::getOrCreateConfig($orgId);
                if ($skuConfig->enabled) {
                    $sku = '[Auto]';
                } else {
                    $errors[] = "Columna 'Código' es obligatoria.";
                    $fieldErrors['sku'] = "Código (SKU) requerido.";
                }
            }

            // Check if product exists in org
            $product = ($sku !== '' && $sku !== '[Auto]')
                ? Product::where('organization_id', $orgId)->where('sku', $sku)->first()
                : null;

            // 2. Validar Nombre (obligatorio para productos nuevos)
            if (!$product && $name === '') {
                $errors[] = "Columna 'Nombre' es requerida para productos nuevos.";
                $fieldErrors['name'] = "Nombre requerido para nuevos productos.";
            }

            // 3. Validar Precio
            $cleanPrice = ExcelProductParser::cleanNumeric($rawPrice);
            if ($cleanPrice === null || $cleanPrice < 0) {
                $errors[] = "Columna 'Precio' debe ser un número válido.";
                $fieldErrors['price'] = "Precio inválido.";
            }

            // 4. Validar Cantidad
            $cleanQuantity = ExcelProductParser::cleanNumeric($rawQuantity);
            if ($cleanQuantity === null || $cleanQuantity <= 0) {
                $errors[] = "Columna 'Cantidad' debe ser un número mayor a 0.";
                $fieldErrors['quantity'] = "Cantidad inválida.";
            }

            // 5. Validar Costo
            $cleanCost = ExcelProductParser::cleanNumeric($rawCost);
            if ($cleanCost === null || $cleanCost < 0) {
                $errors[] = "Columna 'Costo' debe ser un número válido.";
                $fieldErrors['cost'] = "Costo inválido.";
            }

            // 6. Validar Total si viene informado
            $expectedTotal = ($cleanQuantity !== null && $cleanCost !== null) ? round($cleanQuantity * $cleanCost, 2) : 0;
            if ($rawTotal !== '' && $rawTotal !== null) {
                $cleanTotal = ExcelProductParser::cleanNumeric($rawTotal);
                if ($cleanTotal === null) {
                    $errors[] = "Columna 'Total' no tiene un formato numérico válido.";
                    $fieldErrors['total'] = "Total no numérico.";
                } elseif (abs($cleanTotal - $expectedTotal) > 0.05) {
                    $errors[] = "Columna 'Total' ({$cleanTotal}) no coincide con Cantidad × Costo ({$expectedTotal}).";
                    $fieldErrors['total'] = "No coincide con Cantidad × Costo.";
                }
            }

            // 7. Resolver Categoría
            $resolvedCategory = null;
            if ($rawCategory !== '') {
                $resolvedCategory = Category::resolveByPathOrName($rawCategory, $orgId);
                if (!$resolvedCategory) {
                    $errors[] = "Columna 'categoria' ('{$rawCategory}') no existe en la organización.";
                    $fieldErrors['category'] = "Categoría no encontrada.";
                }
            }

            // 8. Resolver Marca
            $resolvedBrand = null;
            if ($rawBrand !== '') {
                $resolvedBrand = Brand::resolveByName($rawBrand, $orgId);
                if (!$resolvedBrand) {
                    $errors[] = "Columna 'Marca' ('{$rawBrand}') no existe en la organización.";
                    $fieldErrors['brand'] = "Marca no encontrada.";
                }
            }

            // 9. Resolver Proveedor
            $resolvedSupplier = null;
            if ($rawSupplier !== '') {
                $resolvedSupplier = $suppliers->first(function ($s) use ($rawSupplier) {
                    return mb_strtolower(trim($s->name)) === mb_strtolower($rawSupplier);
                });
                if (!$resolvedSupplier) {
                    $errors[] = "Columna 'Proveedor' ('{$rawSupplier}') no existe en la organización.";
                    $fieldErrors['supplier'] = "Proveedor no encontrado.";
                }
            }

            $rawRowData = [
                'sku' => $sku,
                'category' => $rawCategory,
                'brand' => $rawBrand,
                'name' => $name ?: ($product ? $product->name : ''),
                'barcode' => $barcode,
                'price' => $rawPrice,
                'quantity' => $rawQuantity,
                'cost' => $rawCost,
                'total' => $rawTotal,
                'supplier' => $rawSupplier,
            ];

            if (!empty($errors)) {
                $invalid[] = [
                    'row' => $rowIndex,
                    'errors' => $errors,
                    'field_errors' => $fieldErrors,
                    'data' => $rawRowData,
                ];
            } else {
                $productItem = [
                    'product_id' => $product ? $product->id : null,
                    'sku' => $sku,
                    'product_name' => $product ? $product->name : $name,
                    'barcode' => $barcode ?: ($product ? $product->barcode : ''),
                    'category_id' => $resolvedCategory ? $resolvedCategory->id : ($product ? $product->category_id : null),
                    'category_name' => $resolvedCategory ? $resolvedCategory->full_path : ($product && $product->category ? $product->category->full_path : null),
                    'brand_id' => $resolvedBrand ? $resolvedBrand->id : ($product ? $product->brand_id : null),
                    'brand_name' => $resolvedBrand ? $resolvedBrand->name : ($product && $product->brand ? $product->brand->name : null),
                    'supplier_id' => $resolvedSupplier ? $resolvedSupplier->id : null,
                    'supplier_name' => $resolvedSupplier ? $resolvedSupplier->name : null,
                    'price' => (float)$cleanPrice,
                    'quantity' => (float)$cleanQuantity,
                    'cost' => (float)$cleanCost,
                    'total' => (float)$expectedTotal,
                ];

                if ($product) {
                    $validExisting[] = $productItem;
                } else {
                    $validNew[] = $productItem;
                }
            }
        }

        return response()->json([
            'valid_existing' => $validExisting,
            'valid_new' => $validNew,
            'invalid' => $invalid,
        ]);
    }
}

