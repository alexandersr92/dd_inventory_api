<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\Seller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function index()
    {
        return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
    }

    public function store(StoreOrganizationRequest $request)
    {
        if ($request->user()->organization()->exists()) {
            return response(['message' => 'User already has an organization'], Response::HTTP_CONFLICT);
        }

        $this->validateUniqueFields($request);

        DB::beginTransaction();
        
        try {
            $owner_id = $request->user()->id;
            $request->merge(['owner_id' => $owner_id]);

            $organization = Organization::create($request->all());

            if ($request->hasFile('logo')) {
                $organization->logo = $request->file('logo')->store('organizationLogo', 'public');
                $organization->save();
            }

            $user = Auth::user();
            
            if (!$user instanceof User) {
                throw new \Exception('User not found or not authenticated');
            }

            $user->update(['organization_id' => $organization->id]);
            $seller = $this->createOwnerSeller($user, $organization);
            $user->update(['seller_id' => $seller->id]);

            DB::commit();

            return response(new OrganizationResource($organization), Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response(['message' => 'Error creating organization: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        $user = Auth::user();
        
        if (!$user->organization_id) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        if ((string)$user->organization_id !== (string)$id) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        $organization = Organization::with(['user', 'stores', 'clients', 'users'])
            ->where('id', $user->organization_id)
            ->first();
            
        if (!$organization) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        if ($user->id !== $organization->owner_id) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        return response(new OrganizationResource($organization), Response::HTTP_OK);
    }

    public function update(UpdateOrganizationRequest $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->organization_id) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        if ((string)$user->organization_id !== (string)$id) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        $organization = Organization::where('id', $user->organization_id)->first();
        
        if (!$organization) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }
        
        if ($user->id !== $organization->owner_id) {
            return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        $this->validateUniqueFields($request, $organization->id);

        if ($request->hasFile('logo')) {
            $organization->logo = $request->file('logo')->store('organizationLogo', 'public');
        }

        $organization->update($request->all());

        return response(new OrganizationResource($organization), Response::HTTP_ACCEPTED);
    }

    public function destroy(Organization $organization)
    {
        $user = Auth::user();
        
        if ($user->organization_id !== $organization->id || $user->id !== $organization->owner_id) {
            return response(['message' => 'Only the organization owner can delete'], Response::HTTP_FORBIDDEN);
        }
        
        $organization->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    private function validateUniqueFields($request, $excludeId = null)
    {
        $emailQuery = Organization::where('email', $request->email);
        $phoneQuery = Organization::where('phone', $request->phone);

        if ($excludeId) {
            $emailQuery->where('id', '!=', $excludeId);
            $phoneQuery->where('id', '!=', $excludeId);
        }

        if ($emailQuery->exists()) {
            throw new \Exception('Email already exists');
        }

        if ($phoneQuery->exists()) {
            throw new \Exception('Phone already exists');
        }
    }

    private function createOwnerSeller(User $user, Organization $organization)
    {
        return Seller::create([
            'organization_id' => $organization->id,
            'name'            => $user->name,
            'code'            => 'OWNER-' . strtoupper(substr($user->id, 0, 6)), 
            'status'          => 'active',
            'is_owner'        => true,
            'pin_hash'        => Hash::make('1234'), 
        ]);
    }
}
