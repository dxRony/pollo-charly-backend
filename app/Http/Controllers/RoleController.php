<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class RoleController extends Controller
{
    #[OA\Get(
        path: '/api/roles',
        operationId: 'listRoles',
        description: 'Obtiene el listado completo de roles disponibles en el sistema para la asignación a usuarios.',
        summary: 'Listar roles del sistema',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de roles obtenido correctamente.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/UserRole')
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acceso denegado (solo Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $roles = Role::query()->select(['id', 'name'])->orderBy('id')->get();

        return response()->json($roles);
    }
}
