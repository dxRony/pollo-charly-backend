<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Supply\CreateSupplyRequest;
use App\Http\Requests\Supply\ToggleSupplyStatusRequest;
use App\Http\Requests\Supply\UpdateSupplyRequest;
use App\Http\Resources\SupplyResource;
use App\Models\Supply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SupplyController extends Controller
{
    #[OA\Get(
        path: '/api/supplies',
        operationId: 'listSupplies',
        description: 'Obtiene el listado paginado de productos e insumos registrados en el almacén, permitiendo filtrar por búsqueda (código o nombre), unidad de medida, estado de activación y alerta de stock bajo.',
        summary: 'Listar productos e insumos de almacén',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por código o nombre del insumo', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'measurement_unit_id', in: 'query', description: 'Filtrar por ID de unidad de medida', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', description: 'Filtrar por estado activo/inactivo', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'low_stock', in: 'query', description: 'Filtrar solo insumos con stock menor o igual al mínimo de referencia', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de insumos obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SupplyResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 25),
                        new OA\Property(property: 'last_page', type: 'integer', example: 2),
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
        $query = Supply::query()->with('measurementUnit');

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('measurement_unit_id')) {
            $query->where('measurement_unit_id', (int) $request->query('measurement_unit_id'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('low_stock') && filter_var($request->query('low_stock'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereColumn('current_stock', '<=', 'minimum_stock');
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $supplies = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => SupplyResource::collection($supplies->items()),
            'current_page' => $supplies->currentPage(),
            'per_page' => $supplies->perPage(),
            'total' => $supplies->total(),
            'last_page' => $supplies->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/supplies',
        operationId: 'createSupply',
        description: 'Registra un nuevo producto o insumo en el almacén con su unidad de medida, cantidad de referencia mínima y costo unitario. La existencia inicial se establece en 0 y solo se modifica mediante movimientos de inventario.',
        summary: 'Registrar un nuevo producto o insumo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        requestBody: new OA\RequestBody(
            description: 'Datos del nuevo insumo',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateSupplyRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Insumo registrado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Insumo registrado exitosamente.'),
                        new OA\Property(property: 'supply', ref: '#/components/schemas/SupplyResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (código duplicado, cantidad negativa, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La cantidad de referencia debe ser un valor positivo mayor a 0.'),
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
                description: 'Acceso denegado (requiere rol Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
        ]
    )]
    public function store(CreateSupplyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Generar código automático si no fue provisto manualmente
        $code = $validated['code'] ?? null;
        if (empty($code)) {
            $nextId = (Supply::query()->max('id') ?? 0) + 1;
            $code = sprintf('INS-%04d', $nextId);
            while (Supply::query()->where('code', $code)->exists()) {
                $nextId++;
                $code = sprintf('INS-%04d', $nextId);
            }
        }

        $supply = Supply::query()->create([
            'code' => $code,
            'name' => $validated['name'],
            'measurement_unit_id' => $validated['measurement_unit_id'],
            'minimum_stock' => $validated['minimum_stock'],
            'unit_cost' => $validated['unit_cost'],
            'current_stock' => 0.00,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $supply->load('measurementUnit');

        return response()->json([
            'message' => 'Insumo registrado exitosamente.',
            'supply' => new SupplyResource($supply),
        ], 201);
    }

    #[OA\Get(
        path: '/api/supplies/{id}',
        operationId: 'getSupply',
        description: 'Obtiene el detalle completo de un insumo o producto específico.',
        summary: 'Consultar información de un insumo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del insumo a consultar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Insumo obtenido exitosamente.',
                content: new OA\JsonContent(ref: '#/components/schemas/SupplyResource')
            ),
            new OA\Response(
                response: 404,
                description: 'Insumo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Insumo no encontrado.')]
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
    public function show(Supply $supply): JsonResponse
    {
        $supply->loadMissing('measurementUnit');

        return response()->json(new SupplyResource($supply));
    }

    #[OA\Put(
        path: '/api/supplies/{id}',
        operationId: 'updateSupply',
        description: 'Actualiza los datos descriptivos, unidad de medida, cantidad de referencia y costo de un insumo sin alterar su existencia física actual ni su historial de movimientos.',
        summary: 'Actualizar un producto o insumo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del insumo a modificar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Datos actualizados del insumo',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateSupplyRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Insumo actualizado exitosamente sin alterar su historial.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Insumo actualizado exitosamente.'),
                        new OA\Property(property: 'supply', ref: '#/components/schemas/SupplyResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación en campos o cantidades.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La cantidad de referencia debe ser un valor positivo mayor a 0.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Insumo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Insumo no encontrado.')]
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
                description: 'Acceso denegado (requiere rol Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
        ]
    )]
    public function update(UpdateSupplyRequest $request, Supply $supply): JsonResponse
    {
        $validated = $request->validated();

        $supply->name = $validated['name'];
        $supply->measurement_unit_id = $validated['measurement_unit_id'];
        $supply->minimum_stock = $validated['minimum_stock'];
        $supply->unit_cost = $validated['unit_cost'];

        if (! empty($validated['code'])) {
            $supply->code = $validated['code'];
        }

        if (array_key_exists('is_active', $validated)) {
            $supply->is_active = (bool) $validated['is_active'];
        }

        // El campo current_stock permanece inalterado para preservar la integridad del historial
        $supply->save();
        $supply->load('measurementUnit');

        return response()->json([
            'message' => 'Insumo actualizado exitosamente.',
            'supply' => new SupplyResource($supply),
        ]);
    }

    #[OA\Delete(
        path: '/api/supplies/{id}',
        operationId: 'deleteSupply',
        description: 'Desactiva un producto o insumo mediante baja lógica (is_active = false) sin eliminar su historial de movimientos, compras ni alertas.',
        summary: 'Desactivar un insumo (baja lógica)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del insumo a desactivar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Insumo desactivado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Insumo desactivado exitosamente (baja lógica).'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Insumo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Insumo no encontrado.')]
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
                description: 'Acceso denegado (requiere rol Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
        ]
    )]
    public function destroy(Supply $supply): JsonResponse
    {
        $supply->is_active = false;
        $supply->save();

        return response()->json([
            'message' => 'Insumo desactivado exitosamente (baja lógica).',
        ]);
    }

    #[OA\Patch(
        path: '/api/supplies/{id}/status',
        operationId: 'toggleSupplyStatus',
        description: 'Activa o desactiva la disponibilidad de un insumo para compras, recetas y movimientos.',
        summary: 'Cambiar estado de activación del insumo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del insumo', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Nuevo estado de activación',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ToggleSupplyStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado actualizado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Insumo activado exitosamente.'),
                        new OA\Property(property: 'supply', ref: '#/components/schemas/SupplyResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Insumo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Insumo no encontrado.')]
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
                description: 'Acceso denegado (requiere rol Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
        ]
    )]
    public function toggleStatus(ToggleSupplyStatusRequest $request, Supply $supply): JsonResponse
    {
        $isActive = $request->boolean('is_active');
        $supply->is_active = $isActive;
        $supply->save();
        $supply->load('measurementUnit');

        return response()->json([
            'message' => $isActive ? 'Insumo activado exitosamente.' : 'Insumo desactivado exitosamente (baja lógica).',
            'supply' => new SupplyResource($supply),
        ]);
    }
}
