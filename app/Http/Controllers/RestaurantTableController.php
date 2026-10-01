<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RestaurantTable\CreateTableRequest;
use App\Http\Requests\RestaurantTable\ToggleTableStatusRequest;
use App\Http\Requests\RestaurantTable\UpdateTableRequest;
use App\Http\Resources\RestaurantTableResource;
use App\Http\Resources\TableStatusResource;
use App\Models\RestaurantTable;
use App\Models\TableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RestaurantTableController extends Controller
{
    #[OA\Get(
        path: '/api/table-statuses',
        operationId: 'listTableStatuses',
        description: 'Obtiene el catálogo de estados disponibles para las mesas del restaurante (disponible, ocupada, mantenimiento, inactiva).',
        summary: 'Listar estados de mesa',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de estados obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TableStatusResource')),
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
        ]
    )]
    public function statuses(): JsonResponse
    {
        $statuses = TableStatus::query()->orderBy('id')->get();

        return response()->json([
            'data' => TableStatusResource::collection($statuses),
        ]);
    }

    #[OA\Get(
        path: '/api/restaurant-tables',
        operationId: 'listRestaurantTables',
        description: 'Obtiene el listado de mesas registradas en el restaurante, con opción a filtrar únicamente las disponibles para asignación a comandas, por estado operativo o por búsqueda.',
        summary: 'Listar mesas del restaurante',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        parameters: [
            new OA\Parameter(name: 'available_only', in: 'query', description: 'Filtrar únicamente mesas disponibles/libres para comanda', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por nombre de estado (disponible, ocupada, mantenimiento, inactiva)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'table_status_id', in: 'query', description: 'Filtrar por ID de estado de mesa', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'min_capacity', in: 'query', description: 'Filtrar mesas con capacidad mínima para N comensales', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por número de mesa', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de mesas por página (opcional, por defecto lista todas)', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de mesas obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/RestaurantTableResource')),
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
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = RestaurantTable::query()->with('status');

        if ($request->has('available_only') && filter_var($request->query('available_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('status', function ($q) {
                $q->where('name', TableStatus::DISPONIBLE);
            });
        }

        if ($request->filled('status')) {
            $statusName = (string) $request->query('status');
            $query->whereHas('status', function ($q) use ($statusName) {
                $q->where('name', $statusName);
            });
        }

        if ($request->filled('table_status_id')) {
            $query->where('table_status_id', (int) $request->query('table_status_id'));
        }

        if ($request->filled('min_capacity')) {
            $query->where('capacity', '>=', (int) $request->query('min_capacity'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('number', 'like', "%{$search}%");
        }

        $query->orderBy('number');

        if ($request->filled('per_page')) {
            $perPage = max(1, min(100, (int) $request->query('per_page')));
            $paginated = $query->paginate($perPage);

            return response()->json([
                'data' => RestaurantTableResource::collection($paginated->items()),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]);
        }

        $tables = $query->get();

        return response()->json([
            'data' => RestaurantTableResource::collection($tables),
        ]);
    }

    #[OA\Get(
        path: '/api/restaurant-tables/{id}',
        operationId: 'getRestaurantTable',
        description: 'Obtiene el detalle completo de una mesa específica del restaurante y su estado actual.',
        summary: 'Consultar detalle de mesa',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la mesa', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle de la mesa obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'table', ref: '#/components/schemas/RestaurantTableResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Mesa no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Mesa no encontrada.')]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')]
                )
            ),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $table = RestaurantTable::query()->with('status')->find($id);

        if (! $table) {
            return response()->json([
                'message' => 'Mesa no encontrada.',
            ], 404);
        }

        return response()->json([
            'table' => new RestaurantTableResource($table),
        ]);
    }

    #[OA\Post(
        path: '/api/restaurant-tables',
        operationId: 'createRestaurantTable',
        description: 'Registra una nueva mesa en el salón con su número identificador único y capacidad de comensales. La mesa se crea en estado inicial "disponible". Exclusivo para administradores.',
        summary: 'Registrar nueva mesa',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        requestBody: new OA\RequestBody(
            description: 'Datos de la nueva mesa',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateTableRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Mesa registrada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Mesa registrada exitosamente.'),
                        new OA\Property(property: 'table', ref: '#/components/schemas/RestaurantTableResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Datos no válidos o número de mesa duplicado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'El número de mesa ya se encuentra registrado.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'No autorizado. Se requiere rol de Administrador.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para gestionar mesas. Se requiere rol de Administrador.')]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')]
                )
            ),
        ]
    )]
    public function store(CreateTableRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $defaultStatus = TableStatus::query()->where('name', TableStatus::DISPONIBLE)->firstOrFail();
        $statusId = ! empty($validated['table_status_id']) ? (int) $validated['table_status_id'] : $defaultStatus->id;

        $table = RestaurantTable::query()->create([
            'number' => (int) $validated['number'],
            'capacity' => (int) $validated['capacity'],
            'table_status_id' => $statusId,
        ]);

        $table->load('status');

        return response()->json([
            'message' => 'Mesa registrada exitosamente.',
            'table' => new RestaurantTableResource($table),
        ], 201);
    }

    #[OA\Put(
        path: '/api/restaurant-tables/{id}',
        operationId: 'updateRestaurantTable',
        description: 'Actualiza el número de mesa, capacidad o estado de una mesa existente. Si se intenta desactivar o poner en mantenimiento, valida que no tenga comandas activas en curso.',
        summary: 'Actualizar mesa',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la mesa', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Campos a modificar de la mesa',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateTableRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mesa actualizada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Mesa actualizada exitosamente.'),
                        new OA\Property(property: 'table', ref: '#/components/schemas/RestaurantTableResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Mesa con comanda activa o número duplicado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No se puede desactivar la mesa porque tiene una comanda activa en curso.')]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Mesa no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Mesa no encontrada.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'No autorizado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para gestionar mesas. Se requiere rol de Administrador.')]
                )
            ),
        ]
    )]
    public function update(UpdateTableRequest $request, int $id): JsonResponse
    {
        $table = RestaurantTable::query()->with('status')->find($id);

        if (! $table) {
            return response()->json([
                'message' => 'Mesa no encontrada.',
            ], 404);
        }

        $validated = $request->validated();

        // Determinar el nuevo estado si fue especificado
        if (! empty($validated['status'])) {
            $targetStatus = TableStatus::query()->where('name', $validated['status'])->firstOrFail();
            $targetStatusId = $targetStatus->id;
        } elseif (! empty($validated['table_status_id'])) {
            $targetStatusId = (int) $validated['table_status_id'];
            $targetStatus = TableStatus::query()->findOrFail($targetStatusId);
        } else {
            $targetStatus = null;
            $targetStatusId = null;
        }

        // Si se cambia a inactiva o mantenimiento, validar que no tenga comandas activas
        if ($targetStatus && in_array($targetStatus->name, [TableStatus::INACTIVA, TableStatus::MANTENIMIENTO], true)) {
            if ($table->hasActiveOrders()) {
                return response()->json([
                    'message' => "No se puede cambiar el estado de la mesa a '{$targetStatus->name}' porque tiene una comanda activa en curso.",
                ], 422);
            }

            if ($table->status?->name === TableStatus::OCUPADA) {
                return response()->json([
                    'message' => 'No se puede desactivar la mesa porque se encuentra actualmente ocupada.',
                ], 422);
            }
        }

        if (isset($validated['number'])) {
            $table->number = (int) $validated['number'];
        }

        if (isset($validated['capacity'])) {
            $table->capacity = (int) $validated['capacity'];
        }

        if ($targetStatusId !== null) {
            $table->table_status_id = $targetStatusId;
        }

        $table->save();
        $table->load('status');

        return response()->json([
            'message' => 'Mesa actualizada exitosamente.',
            'table' => new RestaurantTableResource($table),
        ]);
    }

    #[OA\Patch(
        path: '/api/restaurant-tables/{id}/status',
        operationId: 'toggleRestaurantTableStatus',
        description: 'Cambia el estado operativo de una mesa o la desactiva/activa (baja lógica). No permite desactivar mesas que se encuentren actualmente ocupadas o con comandas activas.',
        summary: 'Cambiar estado o desactivar mesa',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la mesa', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Parámetros del nuevo estado',
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ToggleTableStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado de mesa actualizado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Mesa desactivada exitosamente.'),
                        new OA\Property(property: 'table', ref: '#/components/schemas/RestaurantTableResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Mesa con comanda activa u ocupada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No se puede desactivar la mesa porque tiene una comanda activa en curso.')]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Mesa no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Mesa no encontrada.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'No autorizado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para gestionar mesas. Se requiere rol de Administrador.')]
                )
            ),
        ]
    )]
    public function toggleStatus(ToggleTableStatusRequest $request, int $id): JsonResponse
    {
        $table = RestaurantTable::query()->with('status')->find($id);

        if (! $table) {
            return response()->json([
                'message' => 'Mesa no encontrada.',
            ], 404);
        }

        $validated = $request->validated();

        if (! empty($validated['status'])) {
            $targetStatus = TableStatus::query()->where('name', $validated['status'])->firstOrFail();
        } elseif (! empty($validated['table_status_id'])) {
            $targetStatus = TableStatus::query()->findOrFail((int) $validated['table_status_id']);
        } elseif (array_key_exists('is_active', $validated)) {
            $targetStatusName = $validated['is_active'] ? TableStatus::DISPONIBLE : TableStatus::INACTIVA;
            $targetStatus = TableStatus::query()->where('name', $targetStatusName)->firstOrFail();
        } else {
            // Alternar: si está inactiva pasar a disponible; si no, pasar a inactiva
            $targetStatusName = ($table->status?->name === TableStatus::INACTIVA)
                ? TableStatus::DISPONIBLE
                : TableStatus::INACTIVA;
            $targetStatus = TableStatus::query()->where('name', $targetStatusName)->firstOrFail();
        }

        // Si se va a inactivar, verificar que no esté ocupada ni tenga comanda activa
        if ($targetStatus->name === TableStatus::INACTIVA) {
            if ($table->hasActiveOrders()) {
                return response()->json([
                    'message' => 'No se puede desactivar la mesa porque tiene una comanda activa en curso.',
                ], 422);
            }

            if ($table->status?->name === TableStatus::OCUPADA) {
                return response()->json([
                    'message' => 'No se puede desactivar la mesa porque se encuentra actualmente ocupada.',
                ], 422);
            }
        }

        $table->table_status_id = $targetStatus->id;
        $table->save();
        $table->load('status');

        $message = ($targetStatus->name === TableStatus::INACTIVA)
            ? 'Mesa desactivada exitosamente (baja lógica).'
            : "Estado de la mesa actualizado a '{$targetStatus->name}'.";

        return response()->json([
            'message' => $message,
            'table' => new RestaurantTableResource($table),
        ]);
    }

    #[OA\Delete(
        path: '/api/restaurant-tables/{id}',
        operationId: 'deleteRestaurantTable',
        description: 'Elimina una mesa del sistema. Si la mesa ya tiene un historial de comandas asociadas, se desactiva de forma lógica (inactiva) para conservar la integridad del historial. Si no tiene comandas, se elimina permanentemente.',
        summary: 'Eliminar o dar de baja una mesa',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Mesas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la mesa', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mesa eliminada o desactivada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La mesa posee un historial de comandas asociadas, por lo que fue desactivada para preservar la integridad de los datos.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Mesa con comanda activa.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No se puede dar de baja la mesa porque tiene una comanda activa en curso.')]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Mesa no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Mesa no encontrada.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'No autorizado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para gestionar mesas. Se requiere rol de Administrador.')]
                )
            ),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $table = RestaurantTable::query()->with('status')->find($id);

        if (! $table) {
            return response()->json([
                'message' => 'Mesa no encontrada.',
            ], 404);
        }

        if ($table->hasActiveOrders()) {
            return response()->json([
                'message' => 'No se puede dar de baja la mesa porque tiene una comanda activa en curso.',
            ], 422);
        }

        if ($table->orders()->exists()) {
            $inactiveStatus = TableStatus::query()->where('name', TableStatus::INACTIVA)->firstOrFail();
            $table->table_status_id = $inactiveStatus->id;
            $table->save();
            $table->load('status');

            return response()->json([
                'message' => 'La mesa posee un historial de comandas asociadas, por lo que fue desactivada para preservar la integridad de los datos.',
                'table' => new RestaurantTableResource($table),
            ]);
        }

        $table->delete();

        return response()->json([
            'message' => 'Mesa eliminada exitosamente del sistema.',
        ]);
    }
}
