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

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;

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

            // 1. Crear la organización
            $organization = Organization::create($request->all());

            if ($request->hasFile('logo')) {
                $organization->logo = $request->file('logo')->store('organizationLogo', 'public');
                $organization->save();
            }

            $user = Auth::user();
            
            if (!$user instanceof User) {
                throw new \Exception('User not found or not authenticated');
            }

            // 2. Asociar todos los módulos activos del sistema a esta nueva organización
            $modules = Module::where('status', 'active')->get();
            if ($modules->isEmpty()) {
                throw new \Exception('No active modules found in system database. Please run seeders first.');
            }
            foreach ($modules as $mod) {
                $organization->modules()->syncWithoutDetaching([$mod->id => ['status' => 'active']]);
            }

            // 3. Crear el rol 'Owner' (acceso total) para esta organización
            $allPermissions = Permission::all();
            if ($allPermissions->isEmpty()) {
                throw new \Exception('No permissions found in system database. Please run seeders first.');
            }
            $ownerRole = Role::firstOrCreate(
                ['name' => 'Owner', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $ownerRole->syncPermissions($allPermissions);

            // 4. Definir y crear los roles 'Manager' y 'Seller' con sus respectivos permisos
            $sellerPermissions = [
                'invoice.index', 'invoice.show', 'invoice.store',
                'client.index', 'client.show', 'client.store', 'client.update',
                'product.index', 'product.show',
                'inventory.index', 'inventory.show',
                'credit.index', 'credit.show', 'credit.payment',
                'seller.index', 'seller.show',
            ];
            $managerPermissions = array_merge($sellerPermissions, [
                'product.store', 'product.update',
                'inventory.store', 'inventory.update',
                'supplier.index', 'supplier.show', 'supplier.store', 'supplier.update',
                'purchase.index', 'purchase.show', 'purchase.store',
                'report.index', 'report.store', 'report.download',
            ]);

            $managerRole = Role::firstOrCreate(
                ['name' => 'Manager', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $managerRole->syncPermissions($managerPermissions);

            $sellerRole = Role::firstOrCreate(
                ['name' => 'Seller', 'organization_id' => $organization->id],
                ['guard_name' => 'web']
            );
            $sellerRole->syncPermissions($sellerPermissions);

            // 5. Asignar el rol de Owner y vincular la organización al usuario creador
            $user->update([
                'organization_id' => $organization->id,
                'role_id'         => $ownerRole->uuid,
            ]);
            $user->assignRole($ownerRole);

            // 6. Crear el perfil de Vendedor (Seller) para el propietario
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
