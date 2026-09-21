<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendTwoFactorRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class TwoFactorController extends Controller
{
    #[OA\Post(
        path: '/api/2fa/verify',
        operationId: 'verifyTwoFactor',
        description: 'Valida el código temporal de 6 dígitos enviado por correo. Si es válido y está vigente (5 min), emite el token de autenticación Bearer (Sanctum). Incluye bloqueo temporal tras 5 intentos fallidos (15 min).',
        summary: 'Verificar código 2FA y completar inicio de sesión',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            description: 'Correo y código de verificación',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/VerifyTwoFactorRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Código válido. Inicio de sesión completado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Autenticación de dos factores completada exitosamente.'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
                        new OA\Property(property: 'token', type: 'string', example: '1|qWeRtYuIoP1234567890abcdef...'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Código incorrecto, expirado o sin sesión 2FA activa.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El código de verificación ingresado es incorrecto.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: ['code' => ['El código de verificación ingresado es incorrecto.']]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Demasiados intentos fallidos. Bloqueo temporal por 15 minutos.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Demasiados intentos fallidos. La verificación de dos factores ha sido bloqueada temporalmente. Por favor intenta de nuevo en 15 minutos.'),
                    ]
                )
            ),
        ]
    )]
    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));
        $throttleKey = '2fa_verify_' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return response()->json([
                'message' => "Demasiados intentos fallidos. La verificación de dos factores ha sido bloqueada temporalmente. Por favor intenta de nuevo en {$minutes} " . ($minutes === 1 ? 'minuto' : 'minutos') . '.',
            ], 429);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 15 * 60);

            return response()->json([
                'message' => 'El código de verificación ingresado es incorrecto.',
                'errors' => ['code' => ['El código de verificación ingresado es incorrecto.']],
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Tu cuenta ha sido desactivada. Por favor contacta a la administración.',
                'errors' => ['email' => ['Tu cuenta ha sido desactivada. Por favor contacta a la administración.']],
            ], 422);
        }

        if (! $user->two_factor_code || ! $user->two_factor_expires_at) {
            return response()->json([
                'message' => 'No hay una solicitud de verificación activa para esta cuenta. Inicia sesión nuevamente.',
                'errors' => ['code' => ['No hay una solicitud de verificación activa para esta cuenta.']],
            ], 422);
        }

        if (now()->isAfter($user->two_factor_expires_at)) {
            return response()->json([
                'message' => 'El código de verificación ha expirado. Por favor solicita un nuevo código.',
                'errors' => ['code' => ['El código de verificación ha expirado. Por favor solicita un nuevo código.']],
            ], 422);
        }

        if (! hash_equals((string) $user->two_factor_code, (string) $request->validated('code'))) {
            RateLimiter::hit($throttleKey, 15 * 60);

            return response()->json([
                'message' => 'El código de verificación ingresado es incorrecto.',
                'errors' => ['code' => ['El código de verificación ingresado es incorrecto.']],
            ], 422);
        }

        RateLimiter::clear($throttleKey);
        $user->resetTwoFactorCode();

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'message' => 'Autenticación de dos factores completada exitosamente.',
            'user' => new UserResource($user->loadMissing('role')),
            'token' => $token,
        ]);
    }

    #[OA\Post(
        path: '/api/2fa/resend',
        operationId: 'resendTwoFactor',
        description: 'Genera un nuevo código de 6 dígitos con vigencia de 5 minutos y lo envía al correo. Requiere una espera mínima de 60 segundos entre solicitudes.',
        summary: 'Reenviar código de verificación 2FA',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            description: 'Correo electrónico del usuario',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ResendTwoFactorRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Código reenviado satisfactoriamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Se ha enviado un nuevo código de verificación a tu correo electrónico.'),
                    ]
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Demasiadas solicitudes. Debe esperar el tiempo de enfriamiento (60 segundos).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Debes esperar 60 segundos antes de solicitar un nuevo código de verificación.'),
                    ]
                )
            ),
        ]
    )]
    public function resend(ResendTwoFactorRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));
        $cooldownKey = '2fa_resend_' . $email;

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);

            return response()->json([
                'message' => "Debes esperar {$seconds} segundos antes de solicitar un nuevo código de verificación.",
            ], 429);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && $user->is_active && $user->two_factor_enabled) {
            $code = $user->generateTwoFactorCode();
            $user->notify(new TwoFactorCodeNotification($code));
        }

        RateLimiter::hit($cooldownKey, 60);

        return response()->json([
            'message' => 'Se ha enviado un nuevo código de verificación a tu correo electrónico si la cuenta está registrada y requiere 2FA.',
        ]);
    }

    #[OA\Get(
        path: '/api/2fa/status',
        operationId: 'getTwoFactorStatus',
        description: 'Consulta si el usuario autenticado tiene habilitada la verificación de dos factores (2FA).',
        summary: 'Consultar estado del 2FA',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado del 2FA consultado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'two_factor_enabled', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'two_factor_enabled' => (bool) $request->user()->two_factor_enabled,
        ]);
    }

    #[OA\Post(
        path: '/api/2fa/enable',
        operationId: 'enableTwoFactor',
        description: 'Activa la verificación de dos factores para la cuenta del usuario autenticado.',
        summary: 'Habilitar 2FA en la cuenta',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA habilitado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La autenticación de dos factores ha sido habilitada exitosamente.'),
                        new OA\Property(property: 'two_factor_enabled', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
        ]
    )]
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->two_factor_enabled = true;
        $user->save();

        return response()->json([
            'message' => 'La autenticación de dos factores ha sido habilitada exitosamente.',
            'two_factor_enabled' => true,
        ]);
    }

    #[OA\Post(
        path: '/api/2fa/disable',
        operationId: 'disableTwoFactor',
        description: 'Desactiva la verificación de dos factores para la cuenta del usuario autenticado.',
        summary: 'Deshabilitar 2FA en la cuenta',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA deshabilitado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La autenticación de dos factores ha sido deshabilitada exitosamente.'),
                        new OA\Property(property: 'two_factor_enabled', type: 'boolean', example: false),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
        ]
    )]
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->two_factor_enabled = false;
        $user->two_factor_code = null;
        $user->two_factor_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'La autenticación de dos factores ha sido deshabilitada exitosamente.',
            'two_factor_enabled' => false,
        ]);
    }
}
