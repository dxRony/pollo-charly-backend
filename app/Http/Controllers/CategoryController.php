<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CategoryResource',
    title: 'Category Resource',
    description: 'Categoría de platillos del menú',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Pollos'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Piezas individuales, medios pollos y pollos enteros'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        operationId: 'listCategories',
        description: 'Obtiene el listado de categorías disponibles para clasificar platillos en el menú.',
        summary: 'Listar categorías de platillos',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Menú'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de categorías obtenido correctamente.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CategoryResource')
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
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->select(['id', 'name', 'description', 'is_active'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return response()->json($categories);
    }
}
