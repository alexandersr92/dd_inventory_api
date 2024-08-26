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

/**
 * @OA\Schema(
 *     schema="Client",
 *      type="object",
 *      @OA\Property(property="id", type="integer", example=1),
 *      @OA\Property(property="first_name", type="string", example="John"),
 *      @OA\Property(property="last_name", type="string", example="Doe"),
 *      @OA\Property(property="email", type="string", example=" [email protected]"),
 *      @OA\Property(property="phone", type="string", example="123-456-7890"),
 *      @OA\Property(property="address", type="string", example="123 Main St"),
 *      @OA\Property(property="city", type="string", example="Anytown"),
 *      @OA\Property(property="state", type="string", example="CA"),
 *      @OA\Property(property="country", type="string", example="USA"),
 *      @OA\Property(property="postal_code", type="string", example="12345"),
 *      @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */

class ClientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     summary="Get a list of clients",
     *     tags={"Clients"},
     *     @OA\Parameter(
     *        name="per_page",
     *      in="query",
     *    description="Number of clients to return",
     *  required=false,
     * @OA\Schema(
     *  type="integer"
     * )
     * ),
     * @OA\Parameter(
     *       name="search",
     *     in="query",
     *  description="Search for a client",
     * required=false,
     * @OA\Schema(
     *  type="string"
     * )
     * ),
     * @OA\Parameter(
     *      name="sort",
     *    in="query",
     * description="Sort clients by column",
     * required=false,
     * @OA\Schema(
     * type="string"
     * )
     * ),
     * @OA\Parameter(
     *     name="asc",
     *   in="query",
     * description="Sort in ascending order",
     * required=false,
     * @OA\Schema(
     * type="string"
     * )
     * ),
     * @OA\Response(
     *    response=200,
     * description="A list of clients",
     * @OA\JsonContent(
     *   type="array",
     *  @OA\Items(ref="#/components/schemas/Client")
     * )
     * )
     * )
     * 
     * 
     * )
     */

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

    /**
     * @OA\Post(
     *     path="/api/v1/clients",
     *     summary="Create a new client",
     *     tags={"Clients"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "phone"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example=" [email protected]"),
     *             @OA\Property(property="phone", type="string", example="123-456-7890"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="city", type="string", example="Anytown"),
     *             @OA\Property(property="state", type="string", example="CA"),
     *             @OA\Property(property="country", type="string", example="USA"),
     *             @OA\Property(property="postal_code", type="string", example="12345"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *      )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Client created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Client")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
        
     */
    public function store(StoreClientRequest $request)
    {

        $client = Client::create($request->all());
        return response(
            new ClientResource($client),
            Response::HTTP_CREATED
        );
    }

    /**
     * @OA\Get(
     *      path="/api/v1/clients/{client}",
     *      summary="Get a client",  
     *      tags={"Clients"},
     *     @OA\Parameter(
     *        name="client",
     *       in="path",
     *     required=true,
     *    description="Client ID",
     * @OA\Schema(
     *   type="integer"
     * )
     * ),
     * @OA\Response(
     *   response=200,
     * description="A client",
     * @OA\JsonContent(ref="#/components/schemas/Client")
     * )
     * )
     * 
     */
    public function show(Client $client)
    {
        return response(
            new ClientResource($client),
            Response::HTTP_OK
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clients/{client}",
     *     summary="Update a client",
     *     tags={"Clients"},
     *    @OA\Parameter(
     *        name="client",
     *        in="path",
     *        required=true,
     *        description="Client ID",
     *        @OA\Schema(
     *            type="integer"
     *        )
     *    ),
     *    @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(
     *            required={"first_name", "last_name", "email", "phone"},
     *            @OA\Property(property="first_name", type="string", example="John"),
     *            @OA\Property(property="last_name", type="string", example="Doe"),
     *            @OA\Property(property="email", type="string", example=" [email protected]"),
     *            @OA\Property(property="phone", type="string", example="123-456-7890"),
     *            @OA\Property(property="address", type="string", example="123 Main St"),
     *            @OA\Property(property="city", type="string", example="Anytown"),
     *            @OA\Property(property="state", type="string", example="CA"),
     *            @OA\Property(property="country", type="string", example="USA"),
     *            @OA\Property(property="postal_code", type="string", example="12345"),
     *            @OA\Property(property="is_active", type="boolean", example=true)
     *        )
     *    ),
     *    @OA\Response(
     *        response=200,
     *        description="Client updated successfully",
     *        @OA\JsonContent(ref="#/components/schemas/Client")
     *    ),
     *    @OA\Response(
     *        response=422,
     *        description="Validation error",
     *        @OA\JsonContent(
     *            @OA\Property(property="message", type="string")
     *        )
     *     )
     * )
     */
    public function update(UpdateClientRequest $request, Client $client)
    {


        $client->update($request->all());
        return response(
            new ClientResource($client),
            Response::HTTP_OK
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/clients/{client}",
     *     summary="Delete a client",
     *     tags={"Clients"},
     *      @OA\Parameter(
     *         name="client",
     *        in="path",
     *       required=true,
     *     description="Client ID",
     *   @OA\Schema(
     *    type="integer"
     * )
     * ),
     * @OA\Response(
     *    response=204,
     * description="Client deleted successfully"
     * )
     * )
     */


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
