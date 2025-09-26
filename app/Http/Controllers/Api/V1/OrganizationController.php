<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\Seller;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\OrganizationCollection;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{

    public function index()
    {
        return new OrganizationCollection(Organization::all());
    }


    public function store(StoreOrganizationRequest $request)
    {

        //validate is user don't have organization
        if ($request->user()->organization()->exists()) {
            return response(['message' => 'User already has an organization'], Response::HTTP_CONFLICT);
        }

        //validate email is unique
        if (Organization::where('email', $request->email)->exists()) {
            return response(['message' => 'Email already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //validate phone is unique
        if (Organization::where('phone', $request->phone)->exists()) {
            return response(['message' => 'Phone already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $owner_id = $request->user()->id;
        $request->merge(['owner_id' => $owner_id]);

        $organization = Organization::create($request->all());

        if ($request->hasFile('logo')) {
            $organization->logo = $request->file('logo')->store('organizationLogo', 'public');
        }


        $user = Auth::user();

        // Verifica que $user sea una instancia de User
        if (!$user instanceof User) {
            return response(['message' => 'User not found or not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $user->organization_id = $organization->id;

        $user->save();

       
        $user->organization_id = $organization->id;

        $seller = Seller::create([
            'organization_id' => $organization->id,
            'name'            => $user->name,
            'code'            => 'OWNER-' . strtoupper(substr($user->id, 0, 6)), 
            'status'          => 'active',
            'is_owner'        => true,
            'pin_hash'        => Hash::make('1234'), 
        ]);

        $user->seller_id = $seller->id;
        $user->save();

        return response(new OrganizationResource($organization), Response::HTTP_CREATED);
    }


    public function show(Organization $organization)
    {
        return new OrganizationResource($organization);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {


        //validate email is unique but can be the same as the current organization
        if (Organization::where('email', $request->email)->where('id', '!=', $organization->id)->exists()) {
            return response(['message' => 'Email already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }


        //validate phone is unique but can be the same as the current organization
        if (Organization::where('phone', $request->phone)->where('id', '!=', $organization->id)->exists()) {
            return response(['message' => 'Phone already exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $organization->update($request->all());

        return response(new OrganizationResource($organization), Response::HTTP_ACCEPTED);
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
