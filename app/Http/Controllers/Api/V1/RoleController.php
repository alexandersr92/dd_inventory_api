<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Role;
use App\Models\Permission;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\RoleCollection;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Role::class);
        $orgId = Auth::user()->organization_id;
        return new RoleCollection(Role::where('organization_id', $orgId)->get());
    }

    public function premmisionIndex()
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::all();
        return response()->json($permissions, Response::HTTP_OK);
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);

        $orgId = Auth::user()->organization_id;
        if ($role->organization_id != $orgId) {
            return response()->json(['message' => 'Role not found'], Response::HTTP_NOT_FOUND);
        }

        return new RoleResource($role);
    }

    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        //validate unique name

        if (Role::where('name', $request->name)->where('organization_id', Auth::user()->organization_id)->exists()) {
            return response()->json(['message' => 'Role name already exists'], Response::HTTP_CONFLICT);
        }

        // SEGURIDAD: no permitir otorgar permisos que el actor no posee (evita
        // que un sub-rol con role.store se auto-fabrique un rol con permisos
        // superiores a los suyos).
        $this->assertCanGrantPermissions($request->permissions ?? []);

        $orgID = Auth::user()->organization_id;
        // Campos server-side explícitos: organization_id nunca desde el request.
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name,
            'organization_id' => $orgID,
        ]);

        $role->syncPermissions($request->permissions);
        return response()->json(new RoleResource($role), Response::HTTP_CREATED);
    }



    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        if ($role->organization_id != Auth::user()->organization_id) {
            return response()->json(['message' => 'Role not found'], Response::HTTP_NOT_FOUND);
        }

        if (Role::where('name', $request->name)->where('organization_id', Auth::user()->organization_id)->where('uuid', '!=', $role->uuid)->exists()) {
            return response()->json(['message' => 'Role name already exists'], Response::HTTP_CONFLICT);
        }

        // SEGURIDAD: mismo límite de subconjunto que en store().
        $this->assertCanGrantPermissions($request->permissions ?? []);

        // Whitelist explícito: nunca aceptar organization_id del request.
        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);
        return response()->json(new RoleResource($role), Response::HTTP_OK);
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        // SEGURIDAD: Role vive en la conexión 'central' y NO tiene global scope
        // de tenant, así que el route-binding resuelve roles de cualquier
        // organización. Sin este chequeo un admin borra el rol de otro tenant.
        if ($role->organization_id != Auth::user()->organization_id) {
            return response()->json(['message' => 'Role not found'], Response::HTTP_NOT_FOUND);
        }

        $role->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Rechaza cualquier permiso solicitado que el usuario autenticado no posea.
     * El Owner (que tiene todos los permisos) pasa siempre.
     */
    private function assertCanGrantPermissions(array $requested): void
    {
        $ownPermissions = Auth::user()->getAllPermissions()->pluck('name')->all();
        $notAllowed = array_values(array_diff($requested, $ownPermissions));
        if (!empty($notAllowed)) {
            abort(Response::HTTP_FORBIDDEN, 'No puedes otorgar permisos que tú no posees: ' . implode(', ', $notAllowed));
        }
    }
}
