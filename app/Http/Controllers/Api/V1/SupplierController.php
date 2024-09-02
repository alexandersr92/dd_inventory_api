<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierContact;

use Illuminate\Http\Request;
use App\Http\Resources\SupplierCollection;
use App\Http\Resources\SupplierResource;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Requests\StoreSupplierContactRequest;
use App\Http\Requests\UpdateSupplierContactRequest;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        //get organization id from the authenticated user and get all clients for that organization
        $userLoggedIn = Auth::user()->organization_id;
        $order = 'asc';
        if ($request->has('order')) {
            $order = $request->query('order', 'asc');
        }
        $suppliers = Supplier::where('organization_id', $userLoggedIn)
            ->orderBy('name', $order)
            ->paginate($perPage);



        if ($request->has('search')) {
            $order = $request->query('order', 'asc');
            $searchBy = $request->query('search_by', 'name');

            $suppliers = Supplier::where($searchBy, 'like', '%' . $request->search . '%',)
                ->where('organization_id', $userLoggedIn)
                ->orderBy('name', $order)
                ->paginate($perPage);
        }

        if ($request->has('sort')) {
            $order = $request->query('order', 'asc');

            $suppliers = Supplier::orderBy($request->sort, $order)
                ->where('organization_id', $userLoggedIn)
                ->paginate($perPage);
        }




        return new SupplierCollection($suppliers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request)
    {
        $orgId = Auth::user()->organization_id;

        $request->merge(['organization_id' => $orgId]);
        $request->merge(['status' => 'active']);

        $client = Supplier::create($request->all());


        return response(
            new SupplierResource($client),
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        $userLoggedIn = Auth::user()->organization_id;

        if ($supplier->organization_id != $userLoggedIn) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }



        return response(
            new SupplierResource($supplier),
            Response::HTTP_OK
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $userLoggedIn = Auth::user()->organization_id;

        if ($supplier->organization_id != $userLoggedIn) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $supplier->update($request->all());

        return response(
            new SupplierResource($supplier),
            Response::HTTP_OK
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $authUser = Auth::user()->organization_id;

        if ($supplier->organization_id != $authUser) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully'], Response::HTTP_OK);
    }

    //get all contact
    public function contactIndex(Supplier $supplier)
    {
        $userLoggedIn = Auth::user()->organization_id;

        if ($supplier->organization_id != $userLoggedIn) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $contacts = SupplierContact::where('supplier_id', $supplier->id)->get();

        return response(
            $contacts,
            Response::HTTP_OK
        );
    }

    //create contact
    public function contactStore(StoreSupplierContactRequest $contact, Supplier $supplier)

    {
        $userLoggedIn = Auth::user()->organization_id;

        if ($supplier->organization_id != $userLoggedIn) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $contact->merge(['supplier_id' => $supplier->id]);

        $contact = SupplierContact::create($contact->all());

        return response(
            $contact,
            Response::HTTP_CREATED
        );
    }


    //update contact
    public function contactUpdate(UpdateSupplierContactRequest $contactRequest, Supplier $supplier, SupplierContact $contact)
    {
        $userLoggedIn = Auth::user()->organization_id;

        if ($supplier->organization_id != $userLoggedIn) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $contact->update($contactRequest->all());

        return response(
            $contact,
            Response::HTTP_OK
        );
    }


    //delete contact
    public function contactDestroy(Supplier $supplier, SupplierContact $contact)
    {

        $userLoggedIn = Auth::user()->organization_id;

        if ($supplier->organization_id != $userLoggedIn) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $contact->delete();

        return response()->json(['message' => 'Supplier deleted successfully'], Response::HTTP_OK);
    }
}
