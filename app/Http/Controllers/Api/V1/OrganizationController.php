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
    // Días de licencia de prueba otorgados al registrar una organización.
    private const TRIAL_DAYS = 14;

    public function index()
    {
        return response(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
    }

    public function store(StoreOrganizationRequest $request)
    {
        // Guard rápido (la verificación autoritativa se repite dentro de la
        // transacción, con el row del user bloqueado).
        if ($request->user()->organization()->exists()) {
            return response(['message' => 'User already has an organization'], Response::HTTP_CONFLICT);
        }

        $this->validateUniqueFields($request);

        $owner_id = $request->user()->id;
        $request->merge(['owner_id' => $owner_id]);

        // Subir el logo ANTES de la transacción: dentro solo operaciones de BD.
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('organizationLogo', 'public');
        }

        try {
            // TODO el flujo debe correr por UNA sola conexión ('central', donde
            // viven User/Organization/Role/Permission). Mezclar conexiones contra
            // el mismo esquema produce un auto-deadlock: el INSERT de sellers en
            // una conexión deja bloqueada la fila, y el chequeo de FK
            // (users.seller_id -> sellers.id) desde la otra conexión del MISMO
            // request espera ese lock hasta el timeout 1205. Por eso el seller
            // se crea con Seller::on('central') (mismo esquema físico).
            $result = DB::connection('central')->transaction(function () use ($request, $owner_id, $logoPath) {
                // Serializar por usuario: requests concurrentes del mismo user
                // esperan este lock en vez de chocar en el índice único de
                // sellers.code (otra causa del lock wait timeout 1205).
                $user = User::whereKey($owner_id)->lockForUpdate()->first();

                if (!$user instanceof User) {
                    throw new \Exception('User not found or not authenticated');
                }

                // Re-chequeo dentro de la transacción: un request concurrente
                // pudo haber creado la organización mientras esperábamos el lock.
                if ($user->organization()->exists()) {
                    return ['conflict' => true];
                }

                // 1. Crear la organización. SOLO campos de perfil: la licencia
                // (trial) se otorga aparte más abajo. Nunca aceptar
                // license_expires_at / is_lifetime / plan_id / tenancy_type / status
                // desde el request del cliente — si no, un dueño podría
                // auto-otorgarse licencia o marcarse is_lifetime (bypass de cobro).
                $organization = Organization::create([
                    'name'        => $request->name,
                    'email'       => $request->email,
                    'phone'       => $request->phone,
                    'website'     => $request->website,
                    'description' => $request->description,
                    'owner_id'    => $owner_id,
                    'status'      => 'active',
                ]);

                if ($logoPath) {
                    $organization->logo = $logoPath;
                    $organization->save();
                }

                // 1b. Otorgar licencia de prueba (las licencias se manejan por días)
                $trialExpiresAt = now()->addDays(self::TRIAL_DAYS);
                $organization->licenses()->create([
                    'type' => 'add',
                    'days' => self::TRIAL_DAYS,
                    'previous_expires_at' => null,
                    'new_expires_at' => $trialExpiresAt,
                ]);
                $organization->update(['license_expires_at' => $trialExpiresAt]);

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

                return ['organization' => $organization];
            });

            if (isset($result['conflict'])) {
                return response(['message' => 'User already has an organization'], Response::HTTP_CONFLICT);
            }

            // Notificaciones (fuera de la transacción; nunca deben romper el registro).
            $organization = $result['organization'];
            $ownerEmail = $request->user()->email;
            \App\Services\AdminNotifier::notifyRoot(
                'new_account',
                '🎉 Nueva cuenta en DipleBill: ' . $organization->name,
                '<h2>Nueva organización registrada</h2>'
                    . '<p><strong>Nombre:</strong> ' . e($organization->name) . '</p>'
                    . '<p><strong>Propietario:</strong> ' . e($request->user()->name) . ' (' . e($ownerEmail) . ')</p>'
                    . '<p><strong>Prueba gratuita:</strong> ' . self::TRIAL_DAYS . ' días.</p>'
            );
            \App\Services\AdminNotifier::notifyClient(
                $ownerEmail,
                '¡Bienvenido a DipleBill!',
                '<h2>¡Tu cuenta está lista, ' . e($request->user()->name) . '!</h2>'
                    . '<p>Creamos <strong>' . e($organization->name) . '</strong> con <strong>' . self::TRIAL_DAYS . ' días</strong> de prueba gratuita.</p>'
                    . '<p>Ya puedes empezar a facturar. Cualquier duda, estamos para ayudarte.</p>'
            );

            return response(new OrganizationResource($organization), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            // DB::transaction ya hizo rollback; limpiar el logo subido a disco.
            if ($logoPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($logoPath);
            }
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

        // Solo campos de perfil editables por el dueño. Nunca license_expires_at,
        // is_lifetime, plan_id, tenancy_type ni status desde el request del cliente
        // (esos los controla el panel/aprobación de pagos; si no, bypass de cobro).
        $organization->update($request->only(['name', 'email', 'phone', 'website', 'description']));

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
        // Idempotente: un reintento del registro no debe chocar con el índice
        // único de `code` si el seller del owner ya quedó creado.
        // Va por la conexión 'central' (mismo esquema físico que la default)
        // para que participe de la transacción de store() y las FKs
        // (sellers.organization_id, users.seller_id) no crucen conexiones.
        return Seller::on('central')->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'is_owner'        => true,
            ],
            [
                'name'     => $user->name,
                'code'     => 'OWNER-' . strtoupper(substr($user->id, 0, 6)),
                'status'   => 'active',
                'pin_hash' => Hash::make('1234'),
            ]
        );
    }
}
