<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    #[OA\Post(
        path: '/api/forgot-password',
        operationId: 'forgotPassword',
        description: 'Genera un token de recuperación temporal de contraseña con validez de 60 minutos y lo envía al correo del usuario. Por seguridad, siempre retorna la misma respuesta genérica sin revelar si el correo está registrado.',
        summary: 'Solicitar enlace o código de recuperación de contraseña',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            description: 'Correo electrónico del usuario',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Respuesta genérica de confirmación de envío (siempre igual por seguridad para prevenir enumeración de usuarios).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Si el correo existe en el sistema, recibirás un enlace de recuperación de contraseña.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación en el formato del correo.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El formato del correo electrónico no es válido.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        return response()->json([
            'message' => 'Si el correo existe en el sistema, recibirás un enlace de recuperación de contraseña.',
        ]);
    }

    #[OA\Post(
        path: '/api/reset-password',
        operationId: 'resetPassword',
        description: 'Restablece la contraseña del usuario validando el token recibido en su correo (expiración de 60 minutos). Al completarse con éxito, invalida todos los tokens de acceso activos en todos los dispositivos forzando un nuevo inicio de sesión.',
        summary: 'Restablecer contraseña con token de recuperación',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            description: 'Token, correo y nueva contraseña con confirmación',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contraseña restablecida exitosamente. Sesiones previas cerradas.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Contraseña restablecida correctamente. Ya puedes iniciar sesión con tu nueva contraseña.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Token inválido/expirado o contraseña no cumple con los requisitos mínimos (8 caracteres o no coincide confirmación).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El enlace o código de recuperación es inválido o ha expirado. Por favor solicita uno nuevo.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->password = Hash::make($password);
                $user->save();

                // Forzar nuevo login en todos los dispositivos invalidando todos sus tokens activos
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión con tu nueva contraseña.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['El enlace o código de recuperación es inválido o ha expirado. Por favor solicita uno nuevo.'],
        ]);
    }
}
