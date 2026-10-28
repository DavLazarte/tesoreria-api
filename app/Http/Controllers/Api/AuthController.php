<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLogEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y emitir token de Sanctum.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();

        // 1. Verificar existencia y contraseña
        if (!$user || !Hash::check($password, $user->password)) {
            $this->logAccess(
                user: $user,
                emailAttempted: $email,
                event: AccessLogEvent::LoginFailed,
                request: $request
            );

            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son correctas.'],
            ]);
        }

        // 2. Verificar si el usuario está activo
        if (!$user->is_active) {
            $this->logAccess(
                user: $user,
                emailAttempted: $email,
                event: AccessLogEvent::LoginFailed,
                request: $request
            );

            return response()->json([
                'message' => 'Tu usuario se encuentra inactivo. Comunícate con la tesorera o el administrador.',
                'code' => 'user_inactive',
            ], 403);
        }

        // 3. Login exitoso: actualizar fecha, generar token y registrar log
        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logAccess(
            user: $user,
            emailAttempted: $email,
            event: AccessLogEvent::Login,
            request: $request
        );

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Cerrar sesión y revocar el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();

            $this->logAccess(
                user: $user,
                emailAttempted: $user->email,
                event: AccessLogEvent::Logout,
                request: $request
            );
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Obtener el usuario autenticado con sus roles y permisos.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Registra un evento en access_logs.
     */
    protected function logAccess(?User $user, ?string $emailAttempted, AccessLogEvent $event, Request $request): void
    {
        AccessLog::create([
            'user_id' => $user?->id,
            'email_attempted' => $emailAttempted,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
