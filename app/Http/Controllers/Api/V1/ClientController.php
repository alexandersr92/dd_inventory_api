<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientCollection;
use App\Http\Resources\ClientResource;
use Symfony\Component\HttpFoundation\Response;


/**
 * @OA\Schema(
 *     schema="Client",
 *    type="object",
 *    @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="first_name", type="string", example="John"),
 *  @OA\Property(property="last_name", type="string", example="Doe"),
 * @OA\Property(property="email", type="string", example=" [email protected]"),
 * @OA\Property(property="phone", type="string", example="123-456-7890"),
 * @OA\Property(property="address", type="string", example="123 Main St"),
 * @OA\Property(property="city", type="string", example="Anytown"),
 * @OA\Property(property="state", type="string", example="CA"),
 * @OA\Property(property="country", type="string", example="USA"),
 * @OA\Property(property="postal_code", type="string", example="12345"),
 * @OA\Property(property="is_active", type="boolean", example=true)
 * 
 * )
 */

class ClientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     summary="Get a list of clients",
     *     tags={"Clients"},
     *     @OA\Response(
     *         response=200,
     *         description="A list of clients",
     *         @OA\JsonContent(ref="#/components/schemas/ClientCollection")
     *     )
     * )
     */

    public function index()
    {
        return response(
            new ClientCollection(Client::all()),
            Response::HTTP_OK
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clients",
     *     summary="Create a new client",
     *     tags={"Clients"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreClientRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Client created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ClientResource")
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
        //validate email is unique
        if (Client::where('email', $request->email)->exists()) {
            return response(['message' => 'Email already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //validate phone is unique
        if (Client::where('phone', $request->phone)->exists()) {
            return response(['message' => 'Phone already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $client = Client::create($request->all());
        return response(
            new ClientResource($client),
            Response::HTTP_CREATED
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{client}",
     *     summary="Get a specific client",
     *     tags={"Clients"},
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client details",
     *         @OA\JsonContent(ref="#/components/schemas/ClientResource")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
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
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateClientRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ClientResource")
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
    public function update(UpdateClientRequest $request, Client $client)
    {
        //validate email is unique but can be the same as the current client
        if (Client::where('email', $request->email)->where('id', '!=', $client->id)->exists()) {
            return response(['message' => 'Email already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //validate phone is unique but can be the same as the current client
        if (Client::where('phone', $request->phone)->where('id', '!=', $client->id)->exists()) {
            return response(['message' => 'Phone already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
     *     @OA\Parameter(
     *         name="client",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Client deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
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
