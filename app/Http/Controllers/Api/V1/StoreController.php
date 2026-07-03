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
use Illuminate\Support\Facades\Storage;


class StoreController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Store::class);
        $user = Auth::user();
        $organizationId = $user->organization_id;

        // Owner or users with store.index permission get all organization stores
        $isOwner = $user->organization && $user->id === $user->organization->owner_id;

        if ($isOwner || $user->hasPermissionTo('store.index')) {
            $stores = Store::where('organization_id', $organizationId)->get();
        } else {
            $stores = $user->stores;
        }

        return new StoreCollection($stores);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $this->authorize('create', Store::class);

        $orgID = Auth::user()->organization_id;

        //validate name unique
        $store = Store::where('name', $request->name)->where('organization_id', $orgID)->first();
        if ($store) {
            return response([
                'message' => 'Store name already exists'
            ], Response::HTTP_CONFLICT);
        }

        if ($request->zip == '') {

            $request->merge(['zip' => null]);
        }

        $request->merge(['organization_id' => $orgID]);
        $request->merge(['status' => 'active']);


        $store = Store::create($request->all());

        // Asignar automáticamente esta tienda al Seller del Owner (si existe)
        $user = Auth::user();
        if ($user->seller_id) {
             // Ojo: Asegurarse de importar App\Models\Seller o usar la ruta completa
             $seller = \App\Models\Seller::find($user->seller_id);
             if ($seller) {
                 $seller->stores()->attach($store->id, [
                     'organization_id' => $orgID,
                     'status'          => 'active',
                     'assigned_at'     => now(),
                     'created_at'      => now(),
                     'updated_at'      => now(),
                 ]);
             }
        }

        // FIXME: Manejo directo de archivos - debería usar endpoint dedicado de upload
        if ($request->hasFile('print_logo')) {
            $store->print_logo = $request->file('print_logo')->store('stote_print_logo', 'public');
            $store->save(); 
        }


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
        $this->authorize('view', $store);

        return new StoreResource($store);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStoreRequest $request, Store $store)
    {
        $this->authorize('update', $store);

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
        $this->authorize('delete', $store);

        $store->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }


    public function removeImage( Store $store)
    {
        $this->authorize('update', $store);

        if ($store->print_logo) {
            \Storage::disk('public')->delete($store->print_logo);
        }

        $store->print_logo = null;
        $store->save();

        return response(
            new StoreResource($store),
            Response::HTTP_OK
        );
    }
        
    public function addImageToStore(Request $request, Store $store)
    {
        $this->authorize('update', $store);

        // FIXME: Manejo directo de archivos - debería usar endpoint dedicado de upload
        if ($request->hasFile('print_logo')) {
            $store->print_logo = $request->file('print_logo')->store('stote_print_logo', 'public');
        }
        $store->save();

        return response(
            new StoreResource($store),
            Response::HTTP_OK
        );
    }

    public function printLogo(Store $store)
    {
        $this->authorize('view', $store);

        if (!$store || !$store->print_logo) {
            return response()->json(['message' => 'Store or logo not found.'], Response::HTTP_NOT_FOUND);
        }
        $path = 'public/' . $store->print_logo;

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Logo file not found.'], Response::HTTP_NOT_FOUND);
        }

        $file = Storage::get($path);
        $mime = Storage::mimeType($path);

        $base64 = 'data:' . $mime . ';base64,' . base64_encode($file);

        return response()->json(['base64' => $base64]);
    }

    public function updatePrintJson(Request $request, Store $store)
    {
        $this->authorize('update', $store);

        $store->update($request->all());

        return response(
            new StoreResource($store),
            Response::HTTP_ACCEPTED
        );
    }

}
