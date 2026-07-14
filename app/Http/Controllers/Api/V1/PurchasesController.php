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
                $newProduct->sku = $product['sku'];
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
                $newProduct->sku = $product['sku'];
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
    
    public function upload(Request $request)
    {
        $this->authorize('create', Purchases::class);
     
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $file = $request->file('file');

        // Arrays para los resultados
        $validExisting = [];
        $validNew = [];
        $invalid = [];

        // Leer archivo en chunks para evitar problemas de memoria
        Excel::import(new class($validExisting, $validNew, $invalid) implements \Maatwebsite\Excel\Concerns\ToCollection {
            private $validExisting;
            private $validNew;
            private $invalid;

            public function __construct(&$validExisting, &$validNew, &$invalid)
            {
                $this->validExisting = &$validExisting;
                $this->validNew = &$validNew;
                $this->invalid = &$invalid;
            }

            public function collection(Collection $rows)
            {
                foreach ($rows->skip(1) as $index => $row) { // Saltar encabezados
                    $sku = trim($row[0]);
                    $price = trim($row[5]);
                    $quantity = trim($row[6]);
                    $cost = trim($row[7]);

                    $errors = [];

                    //if all fields are empty, skip
                    if (empty($sku) && empty($price) && empty($quantity) && empty($cost)) {
                        continue;
                    }

                    // Validaciones
                    if (empty($sku)) {
                        $errors[] = 'El SKU está vacío';
                    }

                    if (empty($price) || !is_numeric(str_replace('C$', '', $price))) {
                        $errors[] = 'El precio no es válido';
                    }

                    if (empty($quantity) || !is_numeric($quantity)) {
                        $errors[] = 'La cantidad no es válida';
                    }

                    if (empty($cost) || !is_numeric(str_replace('C$', '', $cost))) {
                        $errors[] = 'El costo no es válido';
                    }

                    // Verificar si existe el SKU en la base de datos
                    $exists = Product::where('sku', $sku)->exists();

                    if (empty($errors)) {
                        // Datos válidos
                        if ($exists) {
                            $this->validExisting[] = [
                                'product_id' => Product::where('sku', $sku)->first()->id,
                                'sku' => $sku,
                                'product_name' => trim($row[3]),
                                'barcode' => trim($row[4]),
                                'quantity' => (int)$quantity,
                                'price' => (float)str_replace('C$', '', $price),
                                'cost' => (float)str_replace('C$', '', $cost),
                            ];

                            
                        } else {
                            $this->validNew[] = [
                                'product_id' => null,
                                'sku' => $sku,
                                'product_name' => trim($row[3]),
                                'barcode' => trim($row[4]),
                                'price' => (float)str_replace('C$', '', $price),
                                'quantity' => (int)$quantity,
                                'cost' => (float)str_replace('C$', '', $cost),
                            ];
                        }
                    } else {
                        // Datos inválidos
                        $this->invalid[] = [
                            'row' => $index + 2, // Fila actual (sumar 2 porque se salta encabezado)
                            'errors' => $errors,
                            'data' => [
                                'sku' => $sku,
                                'product_name' => trim($row[3]),
                                'barcode' => trim($row[4]),
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


