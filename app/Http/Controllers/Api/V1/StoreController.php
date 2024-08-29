<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Http\Resources\StoreCollection;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userLoggedIn = Auth::user()->organization_id;

        $store = Store::where('organization_id', $userLoggedIn)->get();

        return new StoreCollection($store);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $orgID = Auth::user()->organization_id;

        //validate name unique
        $store = Store::where('name', $request->name)->where('organization_id', $orgID)->first();
        if ($store) {
            return response([
                'message' => 'Store name already exists'
            ], Response::HTTP_CONFLICT);
        }
        $request->merge(['organization_id' => $orgID]);
        $request->merge(['status' => 'active']);


        $store = Store::create($request->all());

        return response(
            new StoreResource($store),
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Store $store)
    {
        return new StoreResource($store);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoreRequest $request, Store $store)
    {
        $orgID = Auth::user()->organization_id;

        //validate name unique but exclude the current store
        $existingStore = Store::where('name', $request->name)->where('organization_id', $orgID)->where('id', '!=', $store->id)->first();

        if ($existingStore) {
            return response([
                'message' => 'Store name already exists'
            ], Response::HTTP_CONFLICT);
        }


        $store->update($request->all());

        return response(
            new StoreResource($store),
            Response::HTTP_ACCEPTED
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Store $store)
    {
        $store->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
