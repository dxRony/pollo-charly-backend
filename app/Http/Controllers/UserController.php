<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\ResetUserPasswordAction;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\ToggleUserStatusRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        operationId: 'listUsers',
        description: 'Obtiene el listado paginado de usuarios registrados en el sistema, permitiendo filtrar por búsqueda (nombre o correo), rol y estado de activación.',
        summary: 'Listar usuarios (paginado y con filtros)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Término de búsqueda por nombre o correo', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'role_id', in: 'query', description: 'Filtrar por identificador de rol', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', description: 'Filtrar por estado activo (true/1) o inactivo (false/0)', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de usuarios obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/UserResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 3),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                    ]
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
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('role');

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', (int) $request->query('role_id'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'last_page' => $users->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/users',
        operationId: 'createUser',
        description: 'Registra un nuevo usuario con nombre, correo y rol. La contraseña no se define: el sistema genera una temporal aleatoria, la envía por correo y exige cambiarla en el primer inicio de sesión. La cuenta se crea activa por defecto.',
        summary: 'Registrar un nuevo usuario',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        requestBody: new OA\RequestBody(
            description: 'Datos del nuevo usuario',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateUserRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Usuario registrado. La contraseña temporal se envía por correo; si el envío falla, credentials_sent es false y se puede reintentar con el restablecimiento de contraseña.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario registrado exitosamente. Se envió una contraseña temporal a su correo.'),
                        new OA\Property(property: 'credentials_sent', description: 'Indica si el correo con la contraseña temporal pudo enviarse', type: 'boolean', example: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (correo duplicado, rol inválido, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El correo electrónico ya está registrado en el sistema.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
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
    public function store(CreateUserRequest $request, CreateUserAction $createUser): JsonResponse
    {
        $result = $createUser->handle($request->validated());

        return response()->json([
            'message' => $result['credentials_sent']
                ? 'Usuario registrado exitosamente. Se envió una contraseña temporal a su correo.'
                : 'Usuario registrado, pero no se pudo enviar el correo con la contraseña temporal. Usa "Restablecer contraseña" para reintentar.',
            'credentials_sent' => $result['credentials_sent'],
            'user' => new UserResource($result['user']->loadMissing('role')),
        ], 201);
    }

    #[OA\Get(
        path: '/api/users/{id}',
        operationId: 'getUser',
        description: 'Obtiene el detalle completo de un usuario específico.',
        summary: 'Consultar información de un usuario',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario a consultar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Información del usuario obtenida exitosamente.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserResource')
            ),
            new OA\Response(
                response: 404,
                description: 'Usuario no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Usuario no encontrado.')]
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
    public function show(User $user): JsonResponse
    {
        return response()->json(new UserResource($user->loadMissing('role')));
    }

    #[OA\Put(
        path: '/api/users/{id}',
        operationId: 'updateUser',
        description: 'Actualiza los datos de un usuario existente (nombre, correo, rol y opcionalmente contraseña).',
        summary: 'Actualizar un usuario',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario a modificar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Datos actualizados del usuario',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario actualizado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario actualizado exitosamente.'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación o intento de autodesactivación.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El correo electrónico ya está registrado por otro usuario.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Usuario no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Usuario no encontrado.')]
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
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('is_active', $validated) && ! $validated['is_active'] && $request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta de administradora.',
                'errors' => ['is_active' => ['No puedes desactivar tu propia cuenta de administradora.']],
            ], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role_id = $validated['role_id'];

        if (array_key_exists('is_active', $validated)) {
            $user->is_active = (bool) $validated['is_active'];
            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado exitosamente.',
            'user' => new UserResource($user->loadMissing('role')),
        ]);
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        operationId: 'deleteUser',
        description: 'Realiza una baja lógica del usuario (establece is_active = false y revoca sus tokens activos) sin eliminar su historial de operaciones asociadas.',
        summary: 'Desactivar un usuario (baja lógica)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario a desactivar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario desactivado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario desactivado exitosamente (baja lógica).'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Intento de autodesactivación de la cuenta administradora.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No puedes desactivar tu propia cuenta de administradora.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Usuario no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Usuario no encontrado.')]
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
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta de administradora.',
            ], 422);
        }

        $user->is_active = false;
        $user->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Usuario desactivado exitosamente (baja lógica).',
        ]);
    }

    #[OA\Patch(
        path: '/api/users/{id}/status',
        operationId: 'toggleUserStatus',
        description: 'Activa o desactiva la cuenta de un usuario según el valor booleano provisto.',
        summary: 'Cambiar estado de activación del usuario',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Nuevo estado de activación',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ToggleUserStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado actualizado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Usuario activado exitosamente.'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Intento de autodesactivación de la cuenta administradora.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No puedes desactivar tu propia cuenta de administradora.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Usuario no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Usuario no encontrado.')]
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
    public function toggleStatus(ToggleUserStatusRequest $request, User $user): JsonResponse
    {
        $isActive = $request->boolean('is_active');

        if (! $isActive && $request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta de administradora.',
                'errors' => ['is_active' => ['No puedes desactivar tu propia cuenta de administradora.']],
            ], 422);
        }

        $user->is_active = $isActive;
        $user->save();

        if (! $isActive) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $isActive ? 'Usuario activado exitosamente.' : 'Usuario desactivado exitosamente (baja lógica).',
            'user' => new UserResource($user->loadMissing('role')),
        ]);
    }

    #[OA\Post(
        path: '/api/users/{id}/reset-password',
        operationId: 'resetUserPassword',
        description: 'Genera una nueva contraseña temporal aleatoria, la envía al correo del usuario, cierra sus sesiones activas y le exigirá cambiarla al iniciar sesión. También sirve para reenviar las credenciales si el correo original falló. El administrador nunca ve la contraseña.',
        summary: 'Restablecer la contraseña de un usuario con una temporal',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Usuarios'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Identificador del usuario', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contraseña restablecida. credentials_sent indica si el correo pudo enviarse.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Se envió una contraseña temporal al correo del usuario.'),
                        new OA\Property(property: 'credentials_sent', type: 'boolean', example: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/UserResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'No se puede restablecer la propia contraseña desde la administración de usuarios.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Para cambiar tu propia contraseña usa tu perfil.')]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Usuario no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\User].')]
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
    public function resetPassword(Request $request, User $user, ResetUserPasswordAction $resetPassword): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Para cambiar tu propia contraseña usa tu perfil.',
                'errors' => ['user' => ['Para cambiar tu propia contraseña usa tu perfil.']],
            ], 422);
        }

        $credentialsSent = $resetPassword->handle($user);

        return response()->json([
            'message' => $credentialsSent
                ? 'Se envió una contraseña temporal al correo del usuario.'
                : 'Se restableció la contraseña, pero no se pudo enviar el correo. Intenta de nuevo más tarde.',
            'credentials_sent' => $credentialsSent,
            'user' => new UserResource($user->loadMissing('role')),
        ]);
    }
}
