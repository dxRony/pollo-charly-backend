<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Auth\LoginAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        operationId: 'login',
        description: 'Autentica a un usuario existente mediante sus credenciales y emite un token Bearer (Sanctum) para consumir los endpoints protegidos.',
        summary: 'Iniciar sesión en el sistema',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            description: 'Credenciales del usuario',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Autenticación exitosa (retorna token o indica si se requiere verificación 2FA).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'two_factor_required',
                            description: 'Indica si se requiere completar el segundo factor (código por correo)',
                            type: 'boolean',
                            example: false
                        ),
                        new OA\Property(
                            property: 'message',
                            description: 'Mensaje informativo en caso de requerirse 2FA',
                            type: 'string',
                            nullable: true,
                            example: 'Se ha enviado un código de verificación de 6 dígitos a tu correo electrónico.'
                        ),
                        new OA\Property(
                            property: 'email',
                            description: 'Correo al que fue enviado el código 2FA',
                            type: 'string',
                            nullable: true,
                            example: 'admin@pollocharly.com'
                        ),
                        new OA\Property(
                            property: 'user',
                            ref: '#/components/schemas/UserResource'
                        ),
                        new OA\Property(
                            property: 'token',
                            description: 'Token de acceso Bearer (presente cuando 2FA no es requerido)',
                            type: 'string',
                            nullable: true,
                            example: '1|qWeRtYuIoP1234567890abcdef...'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación o credenciales incorrectas / cuenta desactivada.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Las credenciales no coinciden con nuestros registros.'
                        ),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: ['email' => ['Las credenciales no coinciden con nuestros registros.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function login(LoginRequest $request, LoginAction $login): JsonResponse
    {
        $result = $login->handle(
            $request->validated('email'),
            $request->validated('password'),
        );

        if ($result['two_factor_required'] ?? false) {
            return response()->json([
                'two_factor_required' => true,
                'message' => 'Se ha enviado un código de verificación de 6 dígitos a tu correo electrónico.',
                'email' => $result['email'],
            ]);
        }

        return response()->json([
            'two_factor_required' => false,
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        operationId: 'logout',
        description: 'Revoca el token de acceso personal actualmente utilizado por el usuario, finalizando su sesión activa.',
        summary: 'Cerrar sesión (revocar token actual)',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión cerrada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Sesión cerrada correctamente.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado. El token provisto es inválido o no fue enviado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Unauthenticated.'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    #[OA\Get(
        path: '/api/me',
        operationId: 'me',
        description: 'Obtiene los detalles del perfil y rol del usuario correspondiente al token Bearer autenticado.',
        summary: 'Obtener datos del usuario autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Información del usuario autenticado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/UserResource'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado. El token provisto es inválido o no fue enviado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Unauthenticated.'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('role'));
    }
}
