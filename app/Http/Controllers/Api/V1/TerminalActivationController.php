<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Seller;
use App\Models\Store;
use App\Models\TerminalMagicLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TerminalActivationController extends Controller
{
    /**
     * Genera un enlace mágico de un solo uso para vincular una terminal.
     */
    public function generateLink(Request $request)
    {
        $user = Auth::user();
        $orgId = $user->organization_id;

        $validated = $request->validate([
            'store_id'           => ['nullable', 'uuid', 'exists:stores,id'],
            'seller_id'          => ['nullable', 'uuid', 'exists:sellers,id'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'device_name'        => ['nullable', 'string', 'max:100'],
        ]);

        $expiresIn = $validated['expires_in_minutes'] ?? 30;
        $deviceName = $validated['device_name'] ?? 'Terminal POS';
        $expiresAt = now()->addMinutes($expiresIn);

        // Generar token seguro
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        $magicLink = TerminalMagicLink::create([
            'organization_id' => $orgId,
            'user_id'         => $user->id,
            'store_id'        => $validated['store_id'] ?? null,
            'seller_id'       => $validated['seller_id'] ?? null,
            'token_hash'      => $tokenHash,
            'device_name'     => $deviceName,
            'expires_at'      => $expiresAt,
        ]);

        // Cargar datos de la sucursal y vendedor si fueron seleccionados
        $storeData = null;
        if (!empty($validated['store_id'])) {
            $store = Store::find($validated['store_id']);
            if ($store) {
                $storeData = [
                    'id'   => $store->id,
                    'name' => $store->name,
                ];
            }
        }

        $sellerData = null;
        if (!empty($validated['seller_id'])) {
            $seller = Seller::find($validated['seller_id']);
            if ($seller) {
                $sellerData = [
                    'id'   => $seller->id,
                    'name' => $seller->name,
                    'code' => $seller->code,
                ];
            }
        }

        return response()->json([
            'token'              => $plainToken,
            'path'               => "/activate?token={$plainToken}",
            'expires_at'         => $expiresAt->toIso8601String(),
            'expires_in_minutes' => $expiresIn,
            'store'              => $storeData,
            'seller'             => $sellerData,
            'device_name'        => $deviceName,
        ], Response::HTTP_CREATED);
    }

    /**
     * Canjea el enlace de activación de un solo uso por un token de sesión de Sanctum.
     */
    public function claimLink(Request $request)
    {
        $validated = $request->validate([
            'token'       => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $plainToken = $validated['token'];
        $tokenHash = hash('sha256', $plainToken);
        $deviceName = $validated['device_name'] ?? 'Terminal POS';

        // Actualización atómica de 1 solo uso: si ya fue usado o expiró, affected rows es 0
        $affected = TerminalMagicLink::where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update([
                'used_at'            => now(),
                'claimed_ip'         => $request->ip(),
                'claimed_user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

        if ($affected === 0) {
            return response()->json([
                'message' => 'El enlace es inválido, ya fue utilizado o ha expirado.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $magicLink = TerminalMagicLink::where('token_hash', $tokenHash)->first();

        // Obtener el usuario dueño asociado
        $user = User::find($magicLink->user_id);
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que la organización esté activa
        $organization = Organization::find($magicLink->organization_id);
        if ($organization && $organization->status !== 'active') {
            return response()->json([
                'message' => 'La organización está inactiva.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Crear token de Sanctum para la terminal
        $sanctumToken = $user->createToken($deviceName)->plainTextToken;

        // Cargar relaciones del usuario para estructurar la respuesta
        $user->load(['roles.permissions', 'organization.modules', 'stores', 'seller.stores']);

        $rolesData = $user->roles->map(function ($role) {
            return [
                'uuid'        => $role->uuid,
                'name'        => $role->name,
                'permissions' => $role->permissions->map(function ($perm) {
                    return [
                        'name'         => $perm->name,
                        'display_name' => $perm->display_name ?? $perm->name,
                    ];
                }),
            ];
        });

        $orgData = null;
        if ($organization) {
            $orgData = [
                'id'                 => $organization->id,
                'name'               => $organization->name,
                'license_expires_at' => $organization->license_expires_at,
                'is_lifetime'        => $organization->is_lifetime,
                'modules'            => $organization->modules->map(function ($module) {
                    return [
                        'slug'   => $module->slug,
                        'status' => $module->pivot->status ?? 'active',
                    ];
                }),
            ];
        }

        // Datos del vendedor pre-seleccionado o asignado
        $targetSeller = null;
        if ($magicLink->seller_id) {
            $s = Seller::find($magicLink->seller_id);
            if ($s) {
                $targetSeller = [
                    'id'   => $s->id,
                    'name' => $s->name,
                    'code' => $s->code,
                ];
            }
        }

        return response()->json([
            'attributes' => [
                'id'                   => $user->id,
                'name'                 => $user->name,
                'email'                => $user->email,
                'organization_id'      => $user->organization_id,
                'device_name'          => $deviceName,
                'role'                 => $user->role_id,
                'seller_id'            => $targetSeller ? $targetSeller['id'] : $user->seller_id,
                'seller'               => $targetSeller,
                'roles'                => $rolesData,
                'organization'         => $orgData,
                'must_change_password' => (bool) $user->must_change_password,
            ],
            'token'    => $sanctumToken,
            'store_id' => $magicLink->store_id,
            'seller'   => $targetSeller,
        ], Response::HTTP_OK);
    }
}
