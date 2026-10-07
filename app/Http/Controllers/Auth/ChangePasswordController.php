<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ChangePasswordController extends Controller
{
    #[OA\Put(
        path: '/api/me/password',
        operationId: 'changePassword',
        description: 'Permite al usuario autenticado cambiar su propia contraseña verificando la contraseña actual. Cierra todas las demás sesiones activas y conserva la sesión actual.',
        summary: 'Cambiar la contraseña del usuario autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ChangePasswordRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contraseña actualizada correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Contraseña actualizada correctamente. Se cerraron tus otras sesiones activas.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado. El token provisto es inválido o no fue enviado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Contraseña actual incorrecta o nueva contraseña inválida.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La contraseña actual es incorrecta.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: ['current_password' => ['La contraseña actual es incorrecta.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(ChangePasswordRequest $request, ChangePasswordAction $changePassword): JsonResponse
    {
        $changePassword->handle($request->user(), $request->validated('password'));

        return response()->json([
            'message' => 'Contraseña actualizada correctamente. Se cerraron tus otras sesiones activas.',
        ]);
    }
}
