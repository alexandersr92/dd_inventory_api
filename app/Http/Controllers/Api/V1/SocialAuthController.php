<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Services\GoogleOAuthConfigurator;

class SocialAuthController extends Controller
{
    /**
     * Autenticar o registrar un usuario usando Google OAuth (Token-based).
     */
    public function handleGoogle(Request $request)
    {
        $request->validate([
            'token' => 'required|string', // Token de acceso o ID token enviado desde el frontend
            'device_name' => 'required|string',
        ]);

        // Aplicar la configuración dinámica guardada en BD
        GoogleOAuthConfigurator::applyConfiguration();

        if (app()->environment('local', 'testing') && str_starts_with($request->token, 'mock_')) {
            $googleUser = new class($request->token) {
                private string $token;
                public function __construct(string $token) {
                    $this->token = $token;
                }
                public function getEmail() { return 'user_mocked_' . substr(md5($this->token), 0, 6) . '@gmail.com'; }
                public function getId() { return 'mock_google_id_' . substr(md5($this->token), 0, 10); }
                public function getAvatar() { return 'https://lh3.googleusercontent.com/a/default-user=s96-c'; }
                public function getName() { return 'Usuario Mocked ' . substr(md5($this->token), 0, 4); }
            };
        } else {
            try {
                // Obtener datos del usuario desde Google usando el token proveído
                $googleUser = Socialite::driver('google')->userFromToken($request->token);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Token de Google inválido o expirado.',
                    'error' => $e->getMessage()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!$googleUser || !$googleUser->getEmail()) {
            return response()->json([
                'message' => 'No se pudo obtener información del perfil de Google.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();
        $avatar = $googleUser->getAvatar();
        $name = $googleUser->getName() ?? 'Usuario Google';

        // 1. Buscar si el usuario ya está vinculado a este google_id
        $user = User::where('google_id', $googleId)->first();

        // 2. Si no, buscar por email para vincular la cuenta existente
        if (!$user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                // Vincular cuenta existente
                $user->update([
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                ]);
            }
        }

        // 3. Si no existe, crear un nuevo usuario (Registro automático)
        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(16)), // Contraseña aleatoria segura
                'google_id' => $googleId,
                'avatar' => $avatar,
                'status' => 'active',
                'must_change_password' => false, // Autenticado por Google, no requiere cambio forzado
                'organization_id' => null, // El usuario deberá crear su organización después
            ]);
        }

        // Cargar relaciones requeridas por el frontend (idéntico a LoginController)
        $user->load(['roles.permissions', 'organization.modules', 'stores', 'seller.stores']);

        if ($user->organization && $user->organization->status !== 'active') {
            return response()->json([
                'message' => 'La organización de este usuario está inactiva o suspendida.'
            ], Response::HTTP_FORBIDDEN);
        }

        $orgData = null;
        if ($user->organization) {
            $orgData = [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'license_expires_at' => $user->organization->license_expires_at,
                'is_lifetime' => $user->organization->is_lifetime,
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

        $sellerData = null;
        if ($user->seller_id && $user->seller) {
            $sellerData = [
                'id'   => $user->seller->id,
                'name' => $user->seller->name,
                'code' => $user->seller->code,
                'stores' => $user->seller->stores
                    ->filter(fn($s) => ($s->pivot->status ?? 'active') === 'active')
                    ->map(fn($s) => ['id' => $s->id, 'name' => $s->name])
                    ->values(),
            ];
        }

        return response()->json([
            'attributes' => [
                'id' => $user->id,
                'name' => $user->name,
                'organization_id' => $user->organization_id,
                'device_name' => $request->device_name,
                'role' => $user->role_id,
                'seller_id' => $user->seller_id,
                'seller' => $sellerData,
                'roles' => $rolesData,
                'organization' => $orgData,
                'must_change_password' => (bool)$user->must_change_password,
                'avatar' => $user->avatar,
            ],
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ], Response::HTTP_OK);
    }

    /**
     * Vincular la cuenta de Google al usuario logueado actualmente.
     */
    public function linkGoogle(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        GoogleOAuthConfigurator::applyConfiguration();

        if (app()->environment('local', 'testing') && str_starts_with($request->token, 'mock_')) {
            $googleUser = new class($request->token) {
                private string $token;
                public function __construct(string $token) {
                    $this->token = $token;
                }
                public function getEmail() { return 'user_mocked_linked@gmail.com'; }
                public function getId() { return 'mock_google_id_linked_12345'; }
                public function getAvatar() { return 'https://lh3.googleusercontent.com/a/default-user=s96-c'; }
                public function getName() { return 'Mocked Linked User'; }
            };
        } else {
            try {
                $googleUser = Socialite::driver('google')->userFromToken($request->token);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Token de Google inválido o expirado.',
                    'error' => $e->getMessage()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!$googleUser || !$googleUser->getEmail()) {
            return response()->json([
                'message' => 'No se pudo obtener información del perfil de Google.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $googleId = $googleUser->getId();
        $avatar = $googleUser->getAvatar();
        $currentUser = $request->user();

        // Verificar si la cuenta de Google ya está vinculada a otro usuario
        $existingUser = User::where('google_id', $googleId)
            ->where('id', '!=', $currentUser->id)
            ->first();

        if ($existingUser) {
            return response()->json([
                'message' => 'Esta cuenta de Google ya está vinculada a otro usuario del sistema.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Vincular cuenta
        $currentUser->update([
            'google_id' => $googleId,
            'avatar' => $avatar,
        ]);

        return response()->json([
            'message' => 'Tu cuenta de Google ha sido vinculada correctamente.',
            'google_id' => $currentUser->google_id,
            'avatar' => $currentUser->avatar
        ], Response::HTTP_OK);
    }

    /**
     * Desvincular la cuenta de Google del usuario logueado actualmente.
     */
    public function unlinkGoogle(Request $request)
    {
        $currentUser = $request->user();

        // Desvincular cuenta
        $currentUser->update([
            'google_id' => null,
            'avatar' => null,
        ]);

        return response()->json([
            'message' => 'Tu cuenta de Google ha sido desvinculada correctamente.'
        ], Response::HTTP_OK);
    }
}
