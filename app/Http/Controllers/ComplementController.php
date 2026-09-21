<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Complement\CreateComplementRequest;
use App\Http\Requests\Complement\ToggleComplementStatusRequest;
use App\Http\Requests\Complement\UpdateComplementRequest;
use App\Http\Resources\ComplementResource;
use App\Models\Complement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ComplementController extends Controller
{
    #[OA\Get(
        path: '/api/complements',
        operationId: 'listComplements',
        description: 'Obtiene el listado paginado de complementos (extras) registrados en el catálogo, permitiendo filtrar por búsqueda y estado de activación.',
        summary: 'Listar complementos del menú',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por nombre o descripción', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_active', in: 'query', description: 'Filtrar por estado activo/inactivo', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de complementos obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ComplementResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 5),
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
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Complement::query()->with('complementSupplies.supply.measurementUnit');

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $complements = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => ComplementResource::collection($complements->items()),
            'current_page' => $complements->currentPage(),
            'per_page' => $complements->perPage(),
            'total' => $complements->total(),
            'last_page' => $complements->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/complements',
        operationId: 'createComplement',
        description: 'Registra un nuevo complemento (extra) asociando opcionalmente los insumos que se consumen por porción.',
        summary: 'Crear un nuevo complemento',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        requestBody: new OA\RequestBody(
            description: 'Datos del nuevo complemento',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateComplementRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Complemento registrado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Complemento registrado exitosamente.'),
                        new OA\Property(property: 'complement', ref: '#/components/schemas/ComplementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (nombre duplicado, precio inválido, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El nombre del complemento ya existe.'),
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
    public function store(CreateComplementRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $complement = DB::transaction(function () use ($validated) {
            $complement = Complement::query()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'extra_price' => $validated['extra_price'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (! empty($validated['supplies'])) {
                foreach ($validated['supplies'] as $supplyItem) {
                    $complement->complementSupplies()->create([
                        'supply_id' => $supplyItem['supply_id'],
                        'required_quantity' => $supplyItem['required_quantity'],
                    ]);
                }
            }

            return $complement;
        });

        $complement->load('complementSupplies.supply.measurementUnit');

        return response()->json([
            'message' => 'Complemento registrado exitosamente.',
            'complement' => new ComplementResource($complement),
        ], 201);
    }

    #[OA\Get(
        path: '/api/complements/{id}',
        operationId: 'getComplement',
        description: 'Obtiene los datos detallados de un complemento específico y sus insumos asociados.',
        summary: 'Consultar información de un complemento',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del complemento', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complemento obtenido exitosamente.',
                content: new OA\JsonContent(ref: '#/components/schemas/ComplementResource')
            ),
            new OA\Response(
                response: 404,
                description: 'Complemento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Complemento no encontrado.')]
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
    public function show(Complement $complement): JsonResponse
    {
        $complement->loadMissing('complementSupplies.supply.measurementUnit');

        return response()->json(new ComplementResource($complement));
    }

    #[OA\Put(
        path: '/api/complements/{id}',
        operationId: 'updateComplement',
        description: 'Actualiza los datos básicos y la lista de insumos de un complemento existente.',
        summary: 'Actualizar un complemento existente',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del complemento a modificar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Datos actualizados del complemento',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateComplementRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complemento actualizado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Complemento actualizado exitosamente.'),
                        new OA\Property(property: 'complement', ref: '#/components/schemas/ComplementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (nombre duplicado, precio inválido, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El nombre del complemento ya existe.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Complemento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Complemento no encontrado.')]
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
    public function update(UpdateComplementRequest $request, Complement $complement): JsonResponse
    {
        $validated = $request->validated();

        $complement = DB::transaction(function () use ($complement, $validated) {
            $complement->name = $validated['name'];
            $complement->description = $validated['description'] ?? null;
            $complement->extra_price = $validated['extra_price'];

            if (array_key_exists('is_active', $validated)) {
                $complement->is_active = (bool) $validated['is_active'];
            }

            $complement->save();

            if (array_key_exists('supplies', $validated)) {
                $complement->complementSupplies()->delete();
                if (! empty($validated['supplies'])) {
                    foreach ($validated['supplies'] as $supplyItem) {
                        $complement->complementSupplies()->create([
                            'supply_id' => $supplyItem['supply_id'],
                            'required_quantity' => $supplyItem['required_quantity'],
                        ]);
                    }
                }
            }

            return $complement;
        });

        $complement->load('complementSupplies.supply.measurementUnit');

        return response()->json([
            'message' => 'Complemento actualizado exitosamente.',
            'complement' => new ComplementResource($complement),
        ]);
    }

    #[OA\Delete(
        path: '/api/complements/{id}',
        operationId: 'deleteComplement',
        description: 'Desactiva un complemento del menú mediante baja lógica (is_active = false) siempre que no se encuentre asociado a comandas activas pendientes o en preparación.',
        summary: 'Desactivar un complemento (baja lógica)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del complemento a desactivar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complemento desactivado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Complemento desactivado exitosamente (baja lógica).'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'El complemento está en uso en comandas activas.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No se puede desactivar el complemento porque está en uso en comandas activas (pendientes o en preparación).'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Complemento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Complemento no encontrado.')]
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
    public function destroy(Complement $complement): JsonResponse
    {
        if ($complement->hasActiveOrders()) {
            return response()->json([
                'message' => 'No se puede desactivar el complemento porque está en uso en comandas activas (pendientes o en preparación).',
            ], 422);
        }

        $complement->is_active = false;
        $complement->save();

        return response()->json([
            'message' => 'Complemento desactivado exitosamente (baja lógica).',
        ]);
    }

    #[OA\Patch(
        path: '/api/complements/{id}/status',
        operationId: 'toggleComplementStatus',
        description: 'Activa o desactiva la disponibilidad de un complemento en el menú. Si se intenta desactivar, valida que no esté en uso en comandas activas pendientes o en preparación.',
        summary: 'Cambiar estado de disponibilidad del complemento',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del complemento', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Nuevo estado de activación',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ToggleComplementStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado actualizado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Complemento activado exitosamente en el menú.'),
                        new OA\Property(property: 'complement', ref: '#/components/schemas/ComplementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'No se puede desactivar debido a comandas activas.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No se puede desactivar el complemento porque está en uso en comandas activas (pendientes o en preparación).'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Complemento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Complemento no encontrado.')]
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
    public function toggleStatus(ToggleComplementStatusRequest $request, Complement $complement): JsonResponse
    {
        $isActive = $request->boolean('is_active');

        if (! $isActive && $complement->hasActiveOrders()) {
            return response()->json([
                'message' => 'No se puede desactivar el complemento porque está en uso en comandas activas (pendientes o en preparación).',
            ], 422);
        }

        $complement->is_active = $isActive;
        $complement->save();

        return response()->json([
            'message' => $isActive ? 'Complemento activado exitosamente en el menú.' : 'Complemento desactivado exitosamente del menú.',
            'complement' => new ComplementResource($complement->loadMissing('complementSupplies.supply.measurementUnit')),
        ]);
    }
}
