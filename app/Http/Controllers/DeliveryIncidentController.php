<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\CreateDeliveryIncidentRequest;
use App\Http\Requests\Supplier\UpdateDeliveryIncidentStatusRequest;
use App\Http\Resources\DeliveryIncidentResource;
use App\Models\DeliveryIncident;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DeliveryIncidentController extends Controller
{
    #[OA\Get(
        path: '/api/delivery-incidents',
        operationId: 'listDeliveryIncidents',
        description: 'Obtiene el listado general de incidencias de entrega registradas, permitiendo filtrar por proveedor, tipo de incidencia y estado.',
        summary: 'Listar incidencias de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'supplier_id', in: 'query', description: 'Filtrar por ID de proveedor', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'delivery_incident_type_id', in: 'query', description: 'Filtrar por tipo de incidencia', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'delivery_incident_status_id', in: 'query', description: 'Filtrar por estado de la incidencia', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de incidencias obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliveryIncidentResource')),
                        new OA\Property(property: 'total', type: 'integer', example: 5),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryIncident::query()
            ->with(['supplier', 'receivingUser', 'type', 'status', 'purchaseOrder']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
        }

        if ($request->filled('delivery_incident_type_id')) {
            $query->where('delivery_incident_type_id', (int) $request->query('delivery_incident_type_id'));
        }

        if ($request->filled('delivery_incident_status_id')) {
            $query->where('delivery_incident_status_id', (int) $request->query('delivery_incident_status_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $incidents = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => DeliveryIncidentResource::collection($incidents->items()),
            'current_page' => $incidents->currentPage(),
            'per_page' => $incidents->perPage(),
            'total' => $incidents->total(),
            'last_page' => $incidents->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/delivery-incidents',
        operationId: 'createDeliveryIncident',
        description: 'Registra directamente una nueva incidencia de entrega y actualiza el historial del proveedor.',
        summary: 'Registrar incidencia de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateDeliveryIncidentRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Incidencia registrada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/DeliveryIncidentResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Incidencia de entrega registrada exitosamente.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
        ]
    )]
    public function store(CreateDeliveryIncidentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $incident = DB::transaction(function () use ($validated, $user) {
            $incident = DeliveryIncident::create([
                'supplier_id' => $validated['supplier_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'receiving_user_id' => $user->id,
                'delivery_incident_type_id' => $validated['delivery_incident_type_id'],
                'delivery_incident_status_id' => 1, // reportada
                'description' => $validated['description'],
                'evidence_path' => $validated['evidence_path'] ?? null,
            ]);

            if (! empty($validated['purchase_order_id'])) {
                PurchaseOrder::where('id', $validated['purchase_order_id'])
                    ->update([
                        'purchase_order_status_id' => 3, // recibida_con_incidencia
                        'received_date' => now()->toDateString(),
                    ]);
            }

            return $incident;
        });

        $incident->load(['supplier', 'receivingUser', 'type', 'status', 'purchaseOrder']);

        return response()->json([
            'data' => new DeliveryIncidentResource($incident),
            'message' => 'Incidencia de entrega registrada exitosamente. Se actualizó el historial del proveedor.',
        ], 201);
    }

    #[OA\Get(
        path: '/api/delivery-incidents/{id}',
        operationId: 'getDeliveryIncident',
        description: 'Consulta los detalles de una incidencia de entrega.',
        summary: 'Consultar detalle de incidencia de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la incidencia', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Incidencia obtenida correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/DeliveryIncidentResource'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Incidencia no encontrada.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $incident = DeliveryIncident::with(['supplier', 'receivingUser', 'type', 'status', 'purchaseOrder'])
            ->findOrFail($id);

        return response()->json([
            'data' => new DeliveryIncidentResource($incident),
        ]);
    }

    #[OA\Patch(
        path: '/api/delivery-incidents/{id}/status',
        operationId: 'updateDeliveryIncidentStatus',
        description: 'Actualiza el estado de seguimiento de una incidencia (reportada, en corrección o resuelta).',
        summary: 'Actualizar estado de una incidencia de entrega',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la incidencia', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateDeliveryIncidentStatusRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado de incidencia actualizado correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/DeliveryIncidentResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Estado de la incidencia actualizado exitosamente.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Incidencia no encontrada.'),
        ]
    )]
    public function updateStatus(UpdateDeliveryIncidentStatusRequest $request, int $id): JsonResponse
    {
        $incident = DeliveryIncident::findOrFail($id);
        $validated = $request->validated();

        $incident->delivery_incident_status_id = $validated['delivery_incident_status_id'];
        $incident->save();

        $incident->load(['supplier', 'receivingUser', 'type', 'status', 'purchaseOrder']);

        return response()->json([
            'data' => new DeliveryIncidentResource($incident),
            'message' => 'Estado de la incidencia actualizado exitosamente.',
        ]);
    }
}
