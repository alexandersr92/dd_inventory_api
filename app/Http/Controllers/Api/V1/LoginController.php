<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Info(title="Inventory API Documentation", version="0.0.1")
 */
class LoginController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || ! Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Invalid credentials'
            ], Response::HTTP_UNPROCESSABLE_ENTITY); //422

        }



        $user->load(['roles.permissions', 'organization.modules', 'stores']);

        if ($user->organization && $user->organization->status !== 'active') {
            return response([
                'message' => 'La organización de este usuario está inactiva o suspendida.'
            ], Response::HTTP_FORBIDDEN);
        }

        $orgData = null;
        if ($user->organization) {
            $orgData = [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'modules' => $user->organization->modules->map(function($module) {
                    return [
                        'slug' => $module->slug,
                        'status' => $module->pivot->status ?? 'active',
                    ];
                }),
            ];
        }

        $rolesData = $user->roles->map(function($role) {
            return [
                'uuid' => $role->uuid,
                'name' => $role->name,
                'permissions' => $role->permissions->map(function($perm) {
                    return [
                        'name' => $perm->name,
                        'display_name' => $perm->display_name ?? $perm->name,
                    ];
                }),
            ];
        });

        return response()->json([
            'attributes' => [
                'id' => $user->id,
                'name' => $user->name,
                'organization_id' => $user->organization_id,
                'device_name' => $request->device_name,
                'role' => $user->role_id,
                'seller_id' => $user->seller_id,
                'roles' => $rolesData,
                'organization' => $orgData
            ],
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ], Response::HTTP_OK); //200
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ], Response::HTTP_OK); //200
    }

    public function loginSeller(Request $request) {}


    public function registerOwner(Request $request)
    {

        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'password_confirm' => 'required',
            'device_name' => 'required'

        ]);


        if (User::where('email', $request->email)->first()) {
            return response([
                'message' => 'User already registered'
            ], Response::HTTP_UNPROCESSABLE_ENTITY); //422

        }

        if ($request->password !== $request->password_confirm) {
            return response([
                'message' => 'Diferent password'
            ], Response::HTTP_UNPROCESSABLE_ENTITY); //422

        }


        $user = User::create([
            'email' => $request->email,
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'organization_id' => null
        ]);

        //dd($user);
        if ($user) {
            return response()->json([
                'data' => [
                    'attributes' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'organization_id' => $user->organization_id,
                    ],
                    'token' => $user->createToken($request->device_name)->plainTextToken,
                ]
            ], Response::HTTP_CREATED); //201
        }
    }

    public function validationToken(Request $request)
    {
        $user = Auth::user();

        if (Auth::guard('sanctum')->check() && $user) {
            $user->load(['roles.permissions', 'organization.modules', 'stores']);

            if ($user->organization && $user->organization->status !== 'active') {
                return response()->json(['valid' => false, 'message' => 'La organización está inactiva.'], 401);
            }

            $orgData = null;
            if ($user->organization) {
                $orgData = [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                    'modules' => $user->organization->modules->map(function($module) {
                        return [
                            'slug' => $module->slug,
                            'status' => $module->pivot->status ?? 'active',
                        ];
                    }),
                ];
            }

            $rolesData = $user->roles->map(function($role) {
                return [
                    'uuid' => $role->uuid,
                    'name' => $role->name,
                    'permissions' => $role->permissions->map(function($perm) {
                        return [
                            'name' => $perm->name,
                            'display_name' => $perm->display_name ?? $perm->name,
                        ];
                    }),
                ];
            });

            return response()->json(['valid' => true, 'message' => 'Token is valid.', 'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'organization_id' => $user->organization_id,
                'seller_id' => $user->seller_id,
                'roles' => $rolesData,
                'organization' => $orgData
            ]], 200);
        } else {
            return response()->json(['valid' => false, 'message' => 'Token is invalid or expired.'], 401);
        }
    }



    public function registerMember(Request $request) {}

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.'
        ], Response::HTTP_OK);
    }
}

