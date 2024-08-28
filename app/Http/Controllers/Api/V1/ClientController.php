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



        $perPage = $request->query('per_page', 10);
        //get organization id from the authenticated user and get all clients for that organization
        $userLoggedIn = Auth::user()->organization_id;

        $clients = Client::where('organization_id', $userLoggedIn)->get();

        //paginate the clients
        $clients = Client::paginate($perPage);



        if ($request->has('search')) {
            $asc = $request->query('asc', 'true');

            $clients = Client::where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')
                ->orWhere('phone', 'like', '%' . $request->search . '%')
                ->orWhere('address', 'like', '%' . $request->search . '%')
                ->orWhere('city', 'like', '%' . $request->search . '%')
                ->orWhere('state', 'like', '%' . $request->search . '%')
                ->orWhere('country', 'like', '%' . $request->search . '%')
                ->orWhere('is_active', 'like', '%' . $request->search . '%')
                ->orderBy('name', $asc === 'true' ? 'asc' : 'desc')
                ->paginate($perPage);
        }

        if ($request->has('sort')) {
            $asc = $request->query('asc', 'true');

            $clients = Client::orderBy($request->sort, $asc === 'true' ? 'asc' : 'desc')
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
