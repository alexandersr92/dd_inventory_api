<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryDetail;
use Illuminate\Http\Request;

use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventoryCollection;
use App\Http\Resources\InventoryDetailCollection;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orgId = Auth::user()->organization_id;

        $inventories = Inventory::where('organization_id', $orgId)->get();

        return response(
            new InventoryCollection($inventories),
            Response::HTTP_CREATED
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Inventory $inventory)
    {
        return response(
            new InventoryResource($inventory),
            Response::HTTP_OK
        );
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
        } else {
            $inventoryDetails = $inventory->inventoryDetails;
        }

        return response(
            new InventoryDetailCollection($inventoryDetails),
            Response::HTTP_OK
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Inventory $inventory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventory $inventory)
    {
        //
    }
}
