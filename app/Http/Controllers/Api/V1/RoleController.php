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

    public function index()
    {
        $orgId = Auth::user()->organization_id;
        return new RoleCollection(Role::where('organization_id', $orgId)->get());
    }

    public function premmisionIndex()
    {
        $permissions = Permission::all();
        return response()->json($permissions, Response::HTTP_OK);
    }

    public function show(Role $role)
    {
        $orgId = Auth::user()->organization_id;
        if ($role->organization_id != $orgId) {
            return response()->json(['message' => 'Role not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new RoleResource($role), Response::HTTP_OK);
    }

    public function store(StoreRoleRequest $request)
    {
        //validate unique name

        if (Role::where('name', $request->name)->where('organization_id', Auth::user()->organization_id)->exists()) {
            return response()->json(['message' => 'Role name already exists'], Response::HTTP_CONFLICT);
        }

        $orgID = Auth::user()->organization_id;
        $request->merge(['organization_id' => $orgID]);
        $role = Role::create($request->all());


        $permissions = $request->permissions;
        $role->syncPermissions($permissions);
        return response()->json(new RoleResource($role), Response::HTTP_CREATED);
    }



    public function update(UpdateRoleRequest $request, Role $role)
    {
        if ($role->organization_id != Auth::user()->organization_id) {
            return response()->json(['message' => 'Role not found'], Response::HTTP_NOT_FOUND);
        }

        if (Role::where('name', $request->name)->where('organization_id', Auth::user()->organization_id)->where('uuid', '!=', $role->uuid)->exists()) {
            return response()->json(['message' => 'Role name already exists'], Response::HTTP_CONFLICT);
        }
        $role->update($request->all());
        $role->syncPermissions($request->permissions);
        return response()->json(new RoleResource($role), Response::HTTP_OK);
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
