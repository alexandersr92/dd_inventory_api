<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventoryCollection;
use App\Http\Resources\InventoryDetailCollection;
use App\Http\Resources\InventoryInvoiceCollection;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

use App\Http\Resources\InventoryExportCollection;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InventoryExport;


use Illuminate\Support\Collection;


class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;

        $inventories = Inventory::where('organization_id', $orgId)->get();
        $per_page = $request->query('per_page', 20);
        $store = $request->query('store');
        if($store){
            $inventories = Inventory::where('organization_id', $orgId)->where('store_id', $store)->paginate($per_page);
        }

        return response(
            new InventoryCollection($inventories),
            Response::HTTP_CREATED
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryRequest $request)
    {
        $orgId = Auth::user()->organization_id;

        //create inventory and assign to organization and store
        $inventory = Inventory::create([
            'name' => $request->name,
            'description' => $request->description,
            'address' => $request->address,
            'store_id' => $request->store_id,
            'organization_id' => $orgId
        ]);

        return response(
            new InventoryResource($inventory),
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Inventory $inventory, Request $request)
    {
         $inventory->load('store');

        $perPage = $request->query('per_page', 50);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $tagId = $request->query('tag_id');
        $sortBy = $request->query('sort', 'name'); 
        $sortOrder = $request->query('order', 'asc'); 
        $page = $request->query('page', 1);

        $query = $inventory->inventoryDetails()
            ->with(['product.categories', 'product.tags']);

        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                ->orWhere('sku', 'like', "%$search%")
                ->orWhere('barcode', 'like', "%$search%");
            });
        }

     
        if ($categoryId) {
            $query->whereHas('product.categories', function ($q) use ($categoryId) {
                $q->where('id', $categoryId);
            });
        }

        if ($tagId) {
            $query->whereHas('product.tags', function ($q) use ($tagId) {
                $q->where('id', $tagId);
            });
        }

        if (in_array($sortBy, ['name', 'sku', 'price', 'quantity', 'status'])) {
            if (in_array($sortBy, ['name', 'sku'])) {
                $query->join('products', 'inventory_details.product_id', '=', 'products.id')
                    ->orderBy("products.$sortBy", $sortOrder)
                    ->select('inventory_details.*');
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

      
        $details = $query->paginate($perPage);

        return response()->json([
            'inventory' => new InventoryResource($inventory),
            'details' => new InventoryDetailCollection($details),
            'pagination' => [
                'current_page' => $details->currentPage(),
                'per_page' => $details->perPage(),
                'total' => $details->total(),
                'last_page' => $details->lastPage(),
                'next_page_url' => $details->nextPageUrl(),
                'prev_page_url' => $details->previousPageUrl(),
            ],
        ]);
    }

    public function showProducts(Inventory $inventory, Request $request)
    {
        if ($request->has('barcode')) {
            $barcode = $request->query('barcode');
            $inventoryDetails = InventoryDetail::whereHas('product', function ($query) use ($barcode) {
                $query->where('barcode', $barcode);
            })->get();
        } else if ($request->has('sku')) {
            $sku = $request->query('sku');
            $inventoryDetails = InventoryDetail::whereHas('product', function ($query) use ($sku) {
                $query->where('sku', $sku);
            })->get();
        } else if ($request->has('search')){
            $search = $request->query('search');
            $inventoryDetails = InventoryDetail::whereHas('product', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            })->get();
        } else if ($request->has('category')) {
            $category = $request->query('category');

            $inventoryDetails = InventoryDetail::whereHas('product.categories', function ($query) use ($category) {
                $query->where('id', $category);
            })->get();

        } else {
            $inventoryDetails = $inventory->inventoryDetails;
        }

        return new InventoryDetailCollection($inventoryDetails);
    }


    public function addProducts(Inventory $inventory, Request $request)
    {
        $listOfProducts = explode(',', $request->products);

        foreach ($listOfProducts as $product) {
            if (Product::find($product)) {
                $productModel = Product::find($product);

                //validate if product already exists in inventory
                $inventoryDetail = InventoryDetail::where('inventory_id', $inventory->id)
                    ->where('product_id', $productModel->id)
                    ->first();

                if (!$inventoryDetail) {
                   
                    $inventoryDetail = new InventoryDetail();
    
                    $inventoryDetail->inventory_id = $inventory->id;
                    $inventoryDetail->product_id = $productModel->id;
                    $inventoryDetail->quantity = 0;
                    $inventoryDetail->price = $productModel->price;
                    $inventoryDetail->save();
                }
                }
        }

        return response(
            new InventoryResource($inventory),
            Response::HTTP_CREATED
        );
    }

    public function removeProducts(Inventory $inventory, Request $request)
    {
        $listOfProducts = explode(',', $request->products);

        foreach ($listOfProducts as $product) {
            if (Product::find($product)) {
                $productModel = Product::find($product);

                $inventoryDetail = InventoryDetail::where('inventory_id', $inventory->id)
                    ->where('product_id', $productModel->id)
                    ->first();

                if (!$inventoryDetail) {
                    return response(
                        'Product not found in inventory',
                        Response::HTTP_BAD_REQUEST
                    );
                }

                if ($inventoryDetail->quantity > 0) {
                    return response(
                        'Product has quantity in inventory',
                        Response::HTTP_BAD_REQUEST
                    );
                }

                $inventoryDetail->delete();
            }
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryRequest $request, Inventory $inventory)
    {
        $inventory->update($request->all());

        return response(
            new InventoryResource($inventory),
            Response::HTTP_OK
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventory $inventory)
    {

        //validate if inventory has products
        $inventoryDetails = InventoryDetail::where('inventory_id', $inventory->id)->get();
        if ($inventoryDetails->count() > 0) {
            return response(
                'Inventory has products',
                Response::HTTP_BAD_REQUEST
            );
        }

        $inventory->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function getProductInventory(Inventory $inventory, Product $product)
    {
        $inventoryDetail = InventoryDetail::where('inventory_id', $inventory->id)
            ->where('product_id', $product->id)
            ->first();

        return response(
            new InventoryDetailCollection($inventoryDetail),
            Response::HTTP_OK
        );
    }

    public function getProductByStore(Store $store, Request $request){
            
        $search = $request->query('search');

        $inventoryIds = Inventory::where('store_id', $store->id)->pluck('id');

        $inventoryDetails = InventoryDetail::whereIn('inventory_id', $inventoryIds)
            ->when($search, function ($query, $search) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('sku', 'like', "%$search%");
                });
            })
            ->with('product') // Carga la relación para evitar N+1
            ->get();

        return new InventoryInvoiceCollection($inventoryDetails);
    }

    public function exportInventory(Request $request)
    {

        $orgId = Auth::user()->organization_id;
        $inventory = InventoryDetail::where('inventory_id', $request->inventory_id)
            ->get();

    //    $newData = new InventoryExportCollection($inventory); 
        $exportData = (new InventoryExportCollection($inventory))->toArray(request());
     // return $newData;
     return Excel::download(new InventoryExport($exportData), 'reporte_inventario.xlsx');
    }
}
