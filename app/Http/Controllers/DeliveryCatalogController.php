<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryDayResource;
use App\Http\Resources\DeliveryIncidentStatusResource;
use App\Http\Resources\DeliveryIncidentTypeResource;
use App\Models\DeliveryDay;
use App\Models\DeliveryIncidentStatus;
use App\Models\DeliveryIncidentType;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DeliveryCatalogController extends Controller
{
    #[OA\Get(
        path: '/api/delivery-days',
        operationId: 'listDeliveryDays',
        description: 'Obtiene el listado de los días de la semana para entregas de proveedores.',
        summary: 'Listar días de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de días de entrega obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/DeliveryDayResource')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function deliveryDays(): JsonResponse
    {
        $days = DeliveryDay::query()->orderBy('id')->get();

        return response()->json([
            'data' => DeliveryDayResource::collection($days),
        ]);
    }

    #[OA\Get(
        path: '/api/delivery-incident-types',
        operationId: 'listDeliveryIncidentTypes',
        description: 'Obtiene los tipos tipificados de anomalías o incidencias en entregas (peso incompleto, producto dañado, retraso, etc.).',
        summary: 'Listar tipos de incidencias de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de tipos de incidencias obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/DeliveryIncidentTypeResource')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function incidentTypes(): JsonResponse
    {
        $types = DeliveryIncidentType::query()->orderBy('id')->get();

        return response()->json([
            'data' => DeliveryIncidentTypeResource::collection($types),
        ]);
    }

    #[OA\Get(
        path: '/api/delivery-incident-statuses',
        operationId: 'listDeliveryIncidentStatuses',
        description: 'Obtiene los estados de seguimiento de incidencias (reportada, en corrección, resuelta).',
        summary: 'Listar estados de incidencias de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de estados obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/DeliveryIncidentStatusResource')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function incidentStatuses(): JsonResponse
    {
        $statuses = DeliveryIncidentStatus::query()->orderBy('id')->get();

        return response()->json([
            'data' => DeliveryIncidentStatusResource::collection($statuses),
        ]);
    }
}
