<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DailyMenu\SyncDailyMenuBatchRequest;
use App\Http\Requests\DailyMenu\ToggleDailyMenuDishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DailyMenuController extends Controller
{
    #[OA\Get(
        path: '/api/daily-menu',
        operationId: 'getPublicDailyMenu',
        description: 'Endpoint público (sin autenticación) que provee la lista de platillos destacados en el Menú del Día para ser consumido por la landing page.',
        summary: 'Consultar menú del día público (Landing Page)',
        tags: ['Menú del Día'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de platillos activos del menú del día (retorna array vacío si no hay ninguno marcado).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/DishResource')
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $dishes = Dish::query()
            ->where('is_daily_menu', true)
            ->where('is_active', true)
            ->with(['category', 'recipes.supply.measurementUnit', 'complements'])
            ->orderBy('name')
            ->get();

        return response()->json(DishResource::collection($dishes));
    }

    #[OA\Patch(
        path: '/api/dishes/{id}/daily-menu',
        operationId: 'toggleDishDailyMenu',
        description: 'Destaca o retira un platillo específico en el menú del día. Si se intenta destacar (is_daily_menu = true), verifica automáticamente que haya existencias suficientes de todos los insumos de su receta en almacén.',
        summary: 'Destacar o retirar un platillo del menú del día',
        security: [['bearerAuth' => []]],
        tags: ['Menú del Día'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del platillo', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Estado de inclusión en el menú del día',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ToggleDailyMenuDishRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Platillo actualizado exitosamente en el menú del día.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Platillo publicado exitosamente en el menú del día.'),
                        new OA\Property(property: 'dish', ref: '#/components/schemas/DishResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Insumos insuficientes en almacén o platillo inactivo.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El platillo no cuenta con insumos suficientes en almacén para ser publicado en el menú del día.'),
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
    public function toggleDish(ToggleDailyMenuDishRequest $request, Dish $dish): JsonResponse
    {
        $shouldBeDailyMenu = $request->boolean('is_daily_menu');

        if ($shouldBeDailyMenu) {
            if (! $dish->is_active) {
                return response()->json([
                    'message' => 'No se puede destacar un platillo inactivo en el menú del día.',
                    'errors' => ['dish' => ['El platillo está inactivo.']],
                ], 422);
            }

            $availability = $dish->checkSupplyAvailability();

            if (! $availability['available']) {
                $errorMessages = array_map(
                    fn ($item) => "El insumo \"{$item['name']}\" tiene stock insuficiente (requerido: {$item['required']} {$item['unit']}, disponible: {$item['current_stock']} {$item['unit']}).",
                    $availability['insufficient_supplies']
                );

                return response()->json([
                    'message' => 'El platillo no cuenta con insumos suficientes en almacén para ser publicado en el menú del día.',
                    'errors' => ['supplies' => $errorMessages],
                ], 422);
            }

            $dish->update(['is_daily_menu' => true]);

            return response()->json([
                'message' => 'Platillo publicado exitosamente en el menú del día.',
                'dish' => new DishResource($dish->loadMissing(['category', 'recipes.supply.measurementUnit', 'complements'])),
            ]);
        }

        $dish->update(['is_daily_menu' => false]);

        return response()->json([
            'message' => 'Platillo retirado exitosamente del menú del día.',
            'dish' => new DishResource($dish->loadMissing(['category', 'recipes.supply.measurementUnit', 'complements'])),
        ]);
    }

    #[OA\Put(
        path: '/api/daily-menu',
        operationId: 'syncDailyMenuBatch',
        description: 'Actualiza en lote los platillos destacados en el menú del día. Valida que todos los platillos seleccionados estén activos y cuenten con existencias suficientes de sus insumos.',
        summary: 'Publicar selección completa del menú del día',
        security: [['bearerAuth' => []]],
        tags: ['Menú del Día'],
        requestBody: new OA\RequestBody(
            description: 'Lista de IDs de platillos a publicar',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SyncDailyMenuBatchRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Menú del día actualizado y publicado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Menú del día actualizado y publicado exitosamente en la landing page.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DishResource')),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Uno o más platillos no cuentan con stock suficiente o se encuentran inactivos.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No se puede publicar la selección porque uno o más platillos no cuentan con insumos suficientes en almacén.'),
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
    public function updateBatch(SyncDailyMenuBatchRequest $request): JsonResponse
    {
        $dishIds = $request->validated('dish_ids');

        if (empty($dishIds)) {
            Dish::query()->where('is_daily_menu', true)->update(['is_daily_menu' => false]);

            return response()->json([
                'message' => 'Se han retirado todos los platillos del menú del día.',
                'data' => [],
            ]);
        }

        $dishes = Dish::query()
            ->whereIn('id', $dishIds)
            ->with(['recipes.supply.measurementUnit'])
            ->get();

        $errors = [];

        foreach ($dishes as $dish) {
            if (! $dish->is_active) {
                $errors[] = "El platillo \"{$dish->name}\" se encuentra inactivo y no puede publicarse en el menú del día.";
                continue;
            }

            $availability = $dish->checkSupplyAvailability();
            if (! $availability['available']) {
                foreach ($availability['insufficient_supplies'] as $insufficient) {
                    $errors[] = "En \"{$dish->name}\": el insumo \"{$insufficient['name']}\" tiene stock insuficiente (requerido: {$insufficient['required']} {$insufficient['unit']}, disponible: {$insufficient['current_stock']} {$insufficient['unit']}).";
                }
            }
        }

        if (! empty($errors)) {
            return response()->json([
                'message' => 'No se puede publicar la selección porque uno o más platillos no cuentan con insumos suficientes en almacén.',
                'errors' => ['supplies' => $errors],
            ], 422);
        }

        DB::transaction(function () use ($dishIds) {
            Dish::query()->whereNotIn('id', $dishIds)->where('is_daily_menu', true)->update(['is_daily_menu' => false]);
            Dish::query()->whereIn('id', $dishIds)->update(['is_daily_menu' => true]);
        });

        $publishedDishes = Dish::query()
            ->whereIn('id', $dishIds)
            ->with(['category', 'recipes.supply.measurementUnit', 'complements'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Menú del día actualizado y publicado exitosamente en la landing page.',
            'data' => DishResource::collection($publishedDishes),
        ]);
    }
}
