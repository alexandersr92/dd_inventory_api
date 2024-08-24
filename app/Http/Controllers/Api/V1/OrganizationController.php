<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use App\Http\Requests\StoreOrganizationRequest;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\OrganizationResource;
use App\Models\User;

/**
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid", example="c5a72d2d-5c27-4d0b-9a6b-e0063c4f6e87"),
 *            @OA\Property(property="name", type="string", example="Acme Corp"),
 *             @OA\Property(property="address", type="string", example="123 Main St"),
 *             @OA\Property(property="city", type="string", example="Anytown"),
 *             @OA\Property(property="state", type="string", example="CA"),
 *             @OA\Property(property="country", type="string", example="USA"),
 *             @OA\Property(property="postal_code", type="string", example="12345"),
 *             @OA\Property(property="website", type="string", example="https://acme.com"),
 *             @OA\Property(property="logo", type="string", example="https://acme.com/logo.png"),
 *             @OA\Property(property="description", type="string", example="A description of the organization"),
 *             @OA\Property(property="is_active", type="boolean", example=true)
 * 
 * )
 */

class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/organizations",
     *     summary="List all organizations",
     *     tags={"Organizations"},
     *   @OA\Response(
     *        response=200,
     *       description="List of organizations",
     *      @OA\JsonContent(
     *         type="array",
     *        @OA\Items(ref="#/components/schemas/Organization")
     *    )
     * )
     * )
     */
    public function index()
    {
        return Organization::all();
    }

    /**
     * @OA\Post(
     *     path="/api/organizations",
     *     summary="Create a new organization",
     *     tags={"Organizations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "address"},
     *             @OA\Property(property="name", type="string", example="Acme Corp"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="city", type="string", example="Anytown"),
     *             @OA\Property(property="state", type="string", example="CA"),
     *             @OA\Property(property="country", type="string", example="USA"),
     *             @OA\Property(property="postal_code", type="string", example="12345"),
     *             @OA\Property(property="website", type="string", example="https://acme.com"),
     *             @OA\Property(property="logo", type="string", example="https://acme.com/logo.png"),
     *             @OA\Property(property="description", type="string", example="A description of the organization"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     * 
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Organization")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation error")
     *         )
     *     )
     * )
     */
    public function store(StoreOrganizationRequest $request)
    {


        $organization = $request->user()->organization()->create($request->validated());

        return response(new OrganizationResource($organization), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        //
    }
}
