<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\Organization;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    /**
     * List all users in the active organization.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);
        $orgId = Auth::user()->organization_id;
        $users = User::where('organization_id', $orgId)->with(['roles', 'stores'])->get();
        return response()->json($users, Response::HTTP_OK);
    }

    /**
     * Create a new user inside the active organization.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $orgId = Auth::user()->organization_id;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:central.users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:central.roles,uuid',
            'seller_id' => 'nullable|exists:sellers,id',
            'stores' => 'nullable|array',
            'stores.*' => 'exists:stores,id',
        ]);

        // Validate role belongs to the organization
        if ($request->role_id) {
            $role = Role::where('uuid', $request->role_id)->first();
            if ($role && $role->organization_id !== $orgId) {
                return response()->json(['message' => 'Role does not belong to your organization'], Response::HTTP_FORBIDDEN);
            }
        }

        // Validate seller belongs to the organization
        if ($request->seller_id) {
            $seller = Seller::where('id', $request->seller_id)->first();
            if ($seller && $seller->organization_id !== $orgId) {
                return response()->json(['message' => 'El vendedor no pertenece a tu organización'], Response::HTTP_FORBIDDEN);
            }
        }
        
        // Validate stores belong to the organization
        if ($request->stores) {
            $ownedStoresCount = Store::whereIn('id', $request->stores)
                ->where('organization_id', $orgId)
                ->count();
            if ($ownedStoresCount !== count($request->stores)) {
                return response()->json(['message' => 'One or more stores do not belong to your organization'], Response::HTTP_FORBIDDEN);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'organization_id' => $orgId,
            'role_id' => $request->role_id,
            'seller_id' => $request->seller_id,
            'status' => 'active',
            'must_change_password' => true,
        ]);

        if ($request->role_id) {
            $role = Role::where('uuid', $request->role_id)->first();
            if ($role) {
                $user->assignRole($role);
            }
        }

        if ($request->stores) {
            $user->stores()->sync($request->stores);
        }

        event(new \App\Events\UserCreated($user));

        return response()->json($user->load(['roles', 'stores']), Response::HTTP_CREATED);
    }

    /**
     * Show a user details.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $orgId = Auth::user()->organization_id;
        if ($user->organization_id !== $orgId) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($user->load(['roles', 'stores']), Response::HTTP_OK);
    }

    /**
     * Update an existing user in the organization.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $orgId = Auth::user()->organization_id;
        if ($user->organization_id !== $orgId) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('central.users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:central.roles,uuid',
            'seller_id' => 'nullable|exists:sellers,id',
            'stores' => 'nullable|array',
            'stores.*' => 'exists:stores,id',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        // Validate role belongs to the organization
        if ($request->role_id) {
            $role = Role::where('uuid', $request->role_id)->first();
            if ($role && $role->organization_id !== $orgId) {
                return response()->json(['message' => 'Role does not belong to your organization'], Response::HTTP_FORBIDDEN);
            }
        }

        // Validate seller belongs to the organization
        if ($request->has('seller_id') && $request->seller_id) {
            $seller = Seller::where('id', $request->seller_id)->first();
            if ($seller && $seller->organization_id !== $orgId) {
                return response()->json(['message' => 'El vendedor no pertenece a tu organización'], Response::HTTP_FORBIDDEN);
            }
        }

        // Validate stores belong to the organization
        if ($request->has('stores') && $request->stores) {
            $ownedStoresCount = Store::whereIn('id', $request->stores)
                ->where('organization_id', $orgId)
                ->count();
            if ($ownedStoresCount !== count($request->stores)) {
                return response()->json(['message' => 'One or more stores do not belong to your organization'], Response::HTTP_FORBIDDEN);
            }
        }

        $data = $request->only(['name', 'email', 'status', 'role_id']);
        if ($request->has('seller_id')) {
            $data['seller_id'] = $request->seller_id;
        }
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($request->has('role_id')) {
            $user->roles()->detach(); // clear existing roles
            if ($request->role_id) {
                $role = Role::where('uuid', $request->role_id)->first();
                if ($role) {
                    $user->assignRole($role);
                }
            }
        }

        if ($request->has('stores')) {
            $user->stores()->sync($request->stores ?? []);
        }

        return response()->json($user->load(['roles', 'stores']), Response::HTTP_OK);
    }

    /**
     * Delete a user in the organization.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $orgId = Auth::user()->organization_id;
        if ($user->organization_id !== $orgId) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Prevent self-deletion
        if ($user->id === Auth::user()->id) {
            return response()->json(['message' => 'Cannot delete your own user account'], Response::HTTP_BAD_REQUEST);
        }

        $organization = Organization::find($orgId);
        if ($organization && $organization->owner_id === $user->id) {
            return response()->json(['message' => 'Cannot delete the organization owner account'], Response::HTTP_BAD_REQUEST);
        }

        $user->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, $id)
    {
        $orgId = Auth::user()->organization_id;
        $user = User::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $user);

        $request->validate([
            'role_id' => 'required|exists:central.roles,uuid',
        ]);

        $role = Role::where('uuid', $request->role_id)->first();
        if ($role->organization_id !== $orgId) {
            return response()->json(['message' => 'Role does not belong to your organization'], Response::HTTP_FORBIDDEN);
        }

        $user->roles()->detach();
        $user->assignRole($role);
        $user->update(['role_id' => $role->uuid]);

        return response()->json($user->load('roles'), Response::HTTP_OK);
    }

    /**
     * Associate a user to specific stores.
     */
    public function assignStores(Request $request, $id)
    {
        $orgId = Auth::user()->organization_id;
        $user = User::where('id', $id)->where('organization_id', $orgId)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $user);

        $request->validate([
            'stores' => 'required|array',
            'stores.*' => 'exists:stores,id',
        ]);

        $ownedStoresCount = Store::whereIn('id', $request->stores)
            ->where('organization_id', $orgId)
            ->count();

        if ($ownedStoresCount !== count($request->stores)) {
            return response()->json(['message' => 'One or more stores do not belong to your organization'], Response::HTTP_FORBIDDEN);
        }

        $user->stores()->sync($request->stores);

        return response()->json($user->load('stores'), Response::HTTP_OK);
    }
}
