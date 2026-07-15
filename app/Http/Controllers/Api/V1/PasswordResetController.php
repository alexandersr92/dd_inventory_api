<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Mail\PasswordResetCodeMail;
use App\Services\MailConfigurator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetController extends Controller
{
    /**
     * Enviar código de 6 dígitos para recuperación de contraseña.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Limitar solicitudes por IP/Email para evitar abuso (máximo 3 por minuto)
        $throttleKey = 'forgot-password:' . $request->ip() . '|' . $email;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Demasiadas solicitudes. Inténtalo de nuevo en {$seconds} segundos."
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
        RateLimiter::hit($throttleKey, 60);

        // Buscar usuario en la base de datos central
        $user = User::where('email', $email)->first();

        // Si no existe, retornamos éxito genérico para evitar enumeración de cuentas
        if (!$user) {
            return response()->json([
                'message' => 'Si el correo electrónico está registrado, recibirás un código de verificación de 6 dígitos.'
            ], Response::HTTP_OK);
        }

        // Generar código de 6 dígitos
        $code = (string) rand(100000, 999999);

        // Eliminar tokens previos de este correo
        DB::connection('central')
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        // Insertar el nuevo token hasheado en la base de datos central
        DB::connection('central')
            ->table('password_reset_tokens')
            ->insert([
                'email' => $email,
                'token' => Hash::make($code),
                'created_at' => now(),
            ]);

        try {
            // Aplicar configuración de correo activa
            MailConfigurator::applyConfiguration();

            // Enviar correo electrónico
            Mail::to($email)->send(new PasswordResetCodeMail($code, $user->name));
        } catch (\Exception $e) {
            // Logear error interno si falla el envío de correo, pero no romper la respuesta
            \Illuminate\Support\Facades\Log::error('Fallo al enviar correo de recuperación', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Si el correo electrónico está registrado, recibirás un código de verificación de 6 dígitos.'
        ], Response::HTTP_OK);
    }

    /**
     * Restablecer contraseña con código de 6 dígitos.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|numeric|digits:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $email = $request->email;
        $code = $request->code;

        // Limitar intentos de validación del código (máximo 5 por IP en 15 minutos)
        $throttleKey = 'reset-password-attempt:' . $request->ip() . '|' . $email;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);
            return response()->json([
                'message' => "Demasiados intentos fallidos. Inténtalo de nuevo en {$minutes} minutos."
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Obtener el registro del token desde la base de datos central
        $tokenRecord = DB::connection('central')
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$tokenRecord) {
            RateLimiter::hit($throttleKey, 900); // 15 minutos de bloqueo
            return response()->json([
                'message' => 'El código de verificación o correo es inválido.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verificar expiración (60 minutos)
        $expiryTime = 60; // minutos
        if (now()->subMinutes($expiryTime)->gt($tokenRecord->created_at)) {
            DB::connection('central')
                ->table('password_reset_tokens')
                ->where('email', $email)
                ->delete();
            return response()->json([
                'message' => 'El código de verificación ha expirado.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verificar si el código ingresado coincide con el hasheado
        if (!Hash::check($code, $tokenRecord->token)) {
            RateLimiter::hit($throttleKey, 900);
            return response()->json([
                'message' => 'El código de verificación o correo es inválido.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Obtener y actualizar el usuario
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'No pudimos encontrar un usuario con esta dirección de correo.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Actualizar contraseña y limpiar flag de cambio obligatorio
        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        // Revocar todos los tokens Sanctum del usuario por seguridad
        $user->tokens()->delete();

        // Eliminar token usado
        DB::connection('central')
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        // Limpiar el contador de intentos fallidos
        RateLimiter::clear($throttleKey);

        return response()->json([
            'message' => 'Tu contraseña ha sido restablecida con éxito.'
        ], Response::HTTP_OK);
    }
}
