<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Dish\CreateDishRequest;
use App\Http\Requests\Dish\ToggleDishStatusRequest;
use App\Http\Requests\Dish\UpdateDishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DishController extends Controller
{
    #[OA\Get(
        path: '/api/dishes',
        operationId: 'listDishes',
        description: 'Obtiene el listado paginado de platillos registrados, permitiendo filtrar por búsqueda de texto, categoría, menú del día y estado activo.',
        summary: 'Listar platillos del menú',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por nombre o descripción', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', description: 'Filtrar por ID de categoría', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', description: 'Filtrar por estado activo/inactivo', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'is_daily_menu', in: 'query', description: 'Filtrar platillos del menú del día', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de platillos obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DishResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 10),
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
        $query = Dish::query()->with(['category', 'recipes.supply.measurementUnit', 'complements']);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_daily_menu') && $request->query('is_daily_menu') !== null && $request->query('is_daily_menu') !== '') {
            $query->where('is_daily_menu', filter_var($request->query('is_daily_menu'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $dishes = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => DishResource::collection($dishes->items()),
            'current_page' => $dishes->currentPage(),
            'per_page' => $dishes->perPage(),
            'total' => $dishes->total(),
            'last_page' => $dishes->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/dishes',
        operationId: 'createDish',
        description: 'Registra un nuevo platillo en el menú, definiendo opcionalmente su receta de insumos con cantidades por porción y su lista de complementos aplicables.',
        summary: 'Crear un nuevo platillo con receta y complementos',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        requestBody: new OA\RequestBody(
            description: 'Datos del nuevo platillo',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateDishRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Platillo registrado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Platillo registrado exitosamente junto con su receta y complementos.'),
                        new OA\Property(property: 'dish', ref: '#/components/schemas/DishResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (nombre duplicado, precio inválido, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El nombre del platillo ya existe.'),
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
    public function store(CreateDishRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dish = DB::transaction(function () use ($validated) {
            $dish = Dish::query()->create([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'image_url' => $validated['image_url'] ?? null,
                'is_daily_menu' => $validated['is_daily_menu'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (! empty($validated['recipes'])) {
                foreach ($validated['recipes'] as $recipeItem) {
                    $dish->recipes()->create([
                        'supply_id' => $recipeItem['supply_id'],
                        'required_quantity' => $recipeItem['required_quantity'],
                    ]);
                }
            }

            if (! empty($validated['complements'])) {
                $dish->complements()->sync($validated['complements']);
            }

            return $dish;
        });

        $dish->load(['category', 'recipes.supply.measurementUnit', 'complements']);

        return response()->json([
            'message' => 'Platillo registrado exitosamente junto con su receta y complementos.',
            'dish' => new DishResource($dish),
        ], 201);
    }

    #[OA\Get(
        path: '/api/dishes/{id}',
        operationId: 'getDish',
        description: 'Obtiene el detalle completo de un platillo, incluyendo sus insumos de receta y complementos.',
        summary: 'Consultar información de un platillo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del platillo', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Platillo obtenido correctamente.',
                content: new OA\JsonContent(ref: '#/components/schemas/DishResource')
            ),
            new OA\Response(
                response: 404,
                description: 'Platillo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Platillo no encontrado.')]
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
    public function show(Dish $dish): JsonResponse
    {
        $dish->loadMissing(['category', 'recipes.supply.measurementUnit', 'complements']);

        return response()->json(new DishResource($dish));
    }

    #[OA\Put(
        path: '/api/dishes/{id}',
        operationId: 'updateDish',
        description: 'Actualiza los datos básicos, receta y complementos asociados de un platillo existente.',
        summary: 'Actualizar un platillo existente',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del platillo a modificar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Datos modificados del platillo',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateDishRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Platillo actualizado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Platillo actualizado exitosamente.'),
                        new OA\Property(property: 'dish', ref: '#/components/schemas/DishResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (nombre duplicado, precio inválido, etc.).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El nombre del platillo ya existe.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Platillo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Platillo no encontrado.')]
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
    public function update(UpdateDishRequest $request, Dish $dish): JsonResponse
    {
        $validated = $request->validated();

        $dish = DB::transaction(function () use ($dish, $validated) {
            $dish->name = $validated['name'];
            $dish->category_id = $validated['category_id'];
            $dish->description = $validated['description'] ?? null;
            $dish->price = $validated['price'];

            if (array_key_exists('image_url', $validated)) {
                $dish->image_url = $validated['image_url'];
            }

            if (array_key_exists('is_daily_menu', $validated)) {
                $dish->is_daily_menu = (bool) $validated['is_daily_menu'];
            }

            if (array_key_exists('is_active', $validated)) {
                $dish->is_active = (bool) $validated['is_active'];
            }

            $dish->save();

            if (array_key_exists('recipes', $validated)) {
                $dish->recipes()->delete();
                if (! empty($validated['recipes'])) {
                    foreach ($validated['recipes'] as $recipeItem) {
                        $dish->recipes()->create([
                            'supply_id' => $recipeItem['supply_id'],
                            'required_quantity' => $recipeItem['required_quantity'],
                        ]);
                    }
                }
            }

            if (array_key_exists('complements', $validated)) {
                $dish->complements()->sync($validated['complements'] ?? []);
            }

            return $dish;
        });

        $dish->load(['category', 'recipes.supply.measurementUnit', 'complements']);

        return response()->json([
            'message' => 'Platillo actualizado exitosamente.',
            'dish' => new DishResource($dish),
        ]);
    }

    #[OA\Delete(
        path: '/api/dishes/{id}',
        operationId: 'deleteDish',
        description: 'Desactiva un platillo del menú mediante baja lógica (is_active = false) siempre y cuando no existan comandas activas (en estado pendiente o en preparación) asociadas.',
        summary: 'Desactivar un platillo del menú (baja lógica)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del platillo a desactivar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Platillo desactivado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Platillo desactivado exitosamente (baja lógica).'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'El platillo tiene comandas activas en preparación o pendientes.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No se puede desactivar el platillo porque tiene comandas activas (pendientes o en preparación).'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Platillo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Platillo no encontrado.')]
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
    public function destroy(Dish $dish): JsonResponse
    {
        if ($dish->hasActiveOrders()) {
            return response()->json([
                'message' => 'No se puede desactivar el platillo porque tiene comandas activas (pendientes o en preparación).',
            ], 422);
        }

        $dish->is_active = false;
        $dish->save();

        return response()->json([
            'message' => 'Platillo desactivado exitosamente (baja lógica).',
        ]);
    }

    #[OA\Patch(
        path: '/api/dishes/{id}/status',
        operationId: 'toggleDishStatus',
        description: 'Activa o desactiva la disponibilidad de un platillo en el menú. Si se intenta desactivar, valida que no tenga comandas activas pendientes o en preparación.',
        summary: 'Cambiar estado de disponibilidad del platillo',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del platillo', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Nuevo estado de activación',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ToggleDishStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado actualizado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Platillo activado exitosamente en el menú.'),
                        new OA\Property(property: 'dish', ref: '#/components/schemas/DishResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'No se puede desactivar debido a comandas activas.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No se puede desactivar el platillo porque tiene comandas activas (pendientes o en preparación).'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Platillo no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Platillo no encontrado.')]
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
    public function toggleStatus(ToggleDishStatusRequest $request, Dish $dish): JsonResponse
    {
        $isActive = $request->boolean('is_active');

        if (! $isActive && $dish->hasActiveOrders()) {
            return response()->json([
                'message' => 'No se puede desactivar el platillo porque tiene comandas activas (pendientes o en preparación).',
            ], 422);
        }

        $dish->is_active = $isActive;
        $dish->save();

        return response()->json([
            'message' => $isActive ? 'Platillo activado exitosamente en el menú.' : 'Platillo desactivado exitosamente del menú.',
            'dish' => new DishResource($dish->loadMissing(['category', 'recipes.supply.measurementUnit', 'complements'])),
        ]);
    }
}
