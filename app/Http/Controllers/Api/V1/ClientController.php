<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientCollection;
use App\Http\Resources\ClientResource;

use Symfony\Component\HttpFoundation\Response;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class ClientController extends Controller
{

    public function index(Request $request)
    {

        $perPage = $request->query('per_page', 20);
        //get organization id from the authenticated user and get all clients for that organization
        $userLoggedIn = Auth::user()->organization_id;

        $clients = Client::where('organization_id', $userLoggedIn)->paginate($perPage);



        if ($request->has('search')) {
            $order = $request->query('order', 'asc');
            $searchBy = $request->query('search_by', 'name');

            $clients = Client::where($searchBy, 'like', '%' . $request->search . '%',)
                ->where('organization_id', $userLoggedIn)
                ->orderBy('name', $order)
                ->paginate($perPage);
        }

        if ($request->has('sort')) {
            $order = $request->query('order', 'asc');


            $clients = Client::orderBy($request->sort, $order)
                ->where('organization_id', $userLoggedIn)
                ->paginate($perPage);
        }

        if ($request->has('store')) {
            $order = $request->query('order', 'asc');
            $store = $request->query('store');

            //get clients for a specific store, store and client are related many to many
            $clients = Client::whereHas('stores', function ($query) use ($store) {
                $query->where('store_id', $store);
            })->where('organization_id', $userLoggedIn)
                ->orderBy('name', $order)
                ->paginate($perPage);
        }


        return new ClientCollection($clients);
    }

    public function store(StoreClientRequest $request)
    {

        $client = Client::create($request->all());
        return response(
            new ClientResource($client),
            Response::HTTP_CREATED
        );
    }


    public function show(Client $client)
    {
        return response(
            new ClientResource($client),
            Response::HTTP_OK
        );
    }

    public function update(UpdateClientRequest $request, Client $client)
    {


        $client->update($request->all());
        return response(
            new ClientResource($client),
            Response::HTTP_OK
        );
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return response(
            [
                'message' => 'Client deleted successfully'
            ],
            Response::HTTP_NO_CONTENT
        );
    }
}
