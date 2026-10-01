<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MeasurementUnitController extends Controller
{
    #[OA\Get(
        path: '/api/measurement-units',
        operationId: 'listMeasurementUnits',
        description: 'Obtiene el listado completo de unidades de medida registradas en el sistema (kilogramo, gramo, litro, unidad, etc.).',
        summary: 'Listar unidades de medida',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Insumos'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de unidades de medida obtenido exitosamente.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MeasurementUnitResource')
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
        $units = MeasurementUnit::query()->orderBy('name')->get();

        return response()->json(MeasurementUnitResource::collection($units));
    }
}
