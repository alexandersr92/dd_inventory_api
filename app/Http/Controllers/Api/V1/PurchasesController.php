<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchases;
use App\Models\PurchaseDetail;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Product;

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
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $purchases = Purchases::where('organization_id', $orgId)->get();
        return new PurchaseCollection($purchases);

        return response(
            new PurchaseCollection($inventories),
            Response::HTTP_CREATED
        );
    }
   

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseRequest $request)
    {
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
    
        $products = json_decode($request->products, true);

        foreach ($products as $product) {
            //if product id is null, create a new product
            if($product['product_id'] == null){
                $newProduct = new Product();
                $newProduct->organization_id = $orgId;
                $newProduct->sku = $product['sku'];
                $newProduct->name = $product['name'];
                $newProduct->barcode = $product['barcode'];
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
            foreach ($listProducts as $product) {
                //get inventoryDetail
                $inventoryDetail = InventoryDetail::where('inventory_id', $inventory->id)->where('product_id', $product['product_id'])->first();
                if($inventoryDetail){
                    $inventoryDetail->quantity = $inventoryDetail->quantity + $product['quantity'];
                    $inventoryDetail->save();
                }else{
                    
                    $inventoryDetail = new InventoryDetail();
                    $inventoryDetail->inventory_id = $inventory->id;
                    $inventoryDetail->product_id = $product['product_id'];
                    $inventoryDetail->quantity = $product['quantity'];
                    $inventoryDetail->price = $product['price'];
                    $inventoryDetail->save();
                }

            }
        }
        //responder con los resultados
        return response()->json(['message' => 'Purchase created successfully.'], 201);


    }

    /**
     * Display the specified resource.
     */
    public function show(Purchases $purchase)
    {
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
        $purchaseID = $purchase->id;
        //find purchase
        $purchase = Purchases::find($purchaseID);
        //change status to anulled

        $purchase->status = 'cancelled';
        $purchase->save();
        //find purchase details and restore inventory
        $purchaseDetails = PurchaseDetail::where('purchase_id', $purchaseID)->get();

        foreach ($purchaseDetails as $purchaseDetail) {
            
            $inventoryDetail = InventoryDetail::where('inventory_id', $purchase->inventory_id)->where('product_id', $purchaseDetail->product_id)->first();
      
            $inventoryDetail->quantity = $inventoryDetail->quantity - $purchaseDetail->quantity;
            $inventoryDetail->save();
        }

        return response()->json(['message' => 'Purchase deleted successfully.']);
    }
    
    public function upload(Request $request)
    {
     
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


