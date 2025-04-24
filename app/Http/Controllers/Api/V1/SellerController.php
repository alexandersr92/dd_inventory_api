<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreSellerRequest;
use App\Http\Requests\UpdateSellerRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

use App\Http\Resources\SellerResource;
use App\Http\Resources\SellerCollection;


class SellerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {

        $sellers = Seller::all();

        //add filter by store and status
        if(request('store_id')) {
            $sellers = $sellers->where('store_id', request('store_id'));
        }
        if(request('status')) {
            $sellers = $sellers->where('status', request('status'));
        }
        //add filter by organization

        return new SellerCollection($sellers);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSellerRequest $request)
    {   

        $orgId = Auth::user()->organization_id;
        $validated = $request->validated();

        if(
            Seller::where('organization_id', $orgId)
                ->where('code', $validated['code'])
                ->exists()
        ) {
            return response()->json([
                'message' => 'Seller with this code already exists',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    
        $seller = Seller::create([
            'store_id' => $validated['store_id'],
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Seller created successfully',
            'data' => $seller,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Seller $Seller)
    {
        return new SellerResource($Seller);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSellerRequest $request, Seller $Seller)
    {
        //update seller and return unsing resource
        $validated = $request->validated();

        $Seller->update([
            'store_id' => $validated['store_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'status' => $validated['status'],
        ]);
        
        return SellerResource::make($Seller);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Seller $Seller)
    {
        $Seller->delete();

        return response()->json([
            'message' => 'Seller deleted successfully',
        ], Response::HTTP_NO_CONTENT);
    }
}
