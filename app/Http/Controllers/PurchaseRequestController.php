<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\ApprovePurchaseRequestRequest;
use App\Http\Requests\Purchase\CreatePurchaseRequestRequest;
use App\Http\Requests\Purchase\RejectPurchaseRequestRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseRequestStatus;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Notifications\PurchaseOrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OA;

class PurchaseRequestController extends Controller
{
    #[OA\Get(
        path: '/api/purchase-requests',
        operationId: 'listPurchaseRequests',
        description: 'Obtiene el listado paginado de solicitudes de compra generadas a partir de alertas de reposición o ingresadas manualmente.',
        summary: 'Listar solicitudes de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por nombre de estado (pendiente, aprobada, rechazada_sin_comprar, procesada)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'purchase_request_status_id', in: 'query', description: 'Filtrar por ID de estado', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por motivo o nombre de insumo', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de solicitudes obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseRequestResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 5),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseRequest::query()->with([
            'requesterUser',
            'status',
            'items.supply.measurementUnit',
            'supplyAlerts',
            'purchaseOrders.status',
        ]);

        if ($request->filled('status')) {
            $statusName = (string) $request->query('status');
            $query->whereHas('status', fn ($q) => $q->where('name', $statusName));
        } elseif ($request->filled('purchase_request_status_id')) {
            $query->where('purchase_request_status_id', (int) $request->query('purchase_request_status_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('items.supply', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $paginated = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => PurchaseRequestResource::collection($paginated->items()),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    #[OA\Get(
        path: '/api/purchase-requests/statuses',
        operationId: 'listPurchaseRequestStatuses',
        description: 'Obtiene el catálogo de estados disponibles para solicitudes de compra.',
        summary: 'Listar estados de solicitudes de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de estados obtenido exitosamente.'),
        ]
    )]
    public function statuses(): JsonResponse
    {
        $statuses = PurchaseRequestStatus::query()->get();

        return response()->json([
            'data' => $statuses,
        ]);
    }

    #[OA\Get(
        path: '/api/purchase-requests/{id}',
        operationId: 'getPurchaseRequest',
        description: 'Obtiene el detalle de una solicitud de compra con sus insumos, cantidades sugeridas y aprobadas.',
        summary: 'Consultar detalle de solicitud de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la solicitud', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle de la solicitud obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'purchase_request', ref: '#/components/schemas/PurchaseRequestResource')]
                )
            ),
            new OA\Response(response: 404, description: 'Solicitud no encontrada.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $request = PurchaseRequest::query()->with([
            'requesterUser',
            'status',
            'items.supply.measurementUnit',
            'supplyAlerts',
            'purchaseOrders.status',
        ])->find($id);

        if (! $request) {
            return response()->json([
                'message' => 'Solicitud de compra no encontrada.',
            ], 404);
        }

        return response()->json([
            'purchase_request' => new PurchaseRequestResource($request),
        ]);
    }

    #[OA\Post(
        path: '/api/purchase-requests',
        operationId: 'createPurchaseRequest',
        description: 'Crea manualmente una solicitud de compra de insumos con cantidades sugeridas.',
        summary: 'Registrar solicitud de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreatePurchaseRequestRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Solicitud creada exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
        ]
    )]
    public function store(CreatePurchaseRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $pendingStatus = PurchaseRequestStatus::query()->firstOrCreate(
            ['name' => PurchaseRequestStatus::PENDIENTE],
            ['name' => PurchaseRequestStatus::PENDIENTE]
        );

        $purchaseRequest = DB::transaction(function () use ($validated, $user, $pendingStatus) {
            $pr = PurchaseRequest::query()->create([
                'requester_user_id' => $user->id,
                'purchase_request_status_id' => $pendingStatus->id,
                'reason' => $validated['reason'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                PurchaseRequestItem::query()->create([
                    'purchase_request_id' => $pr->id,
                    'supply_id' => $itemData['supply_id'],
                    'suggested_quantity' => $itemData['suggested_quantity'],
                    'approved_quantity' => null,
                ]);
            }

            if (! empty($validated['supply_alert_id'])) {
                $alert = SupplyAlert::query()->find($validated['supply_alert_id']);
                if ($alert) {
                    $alert->purchase_request_id = $pr->id;
                    $alert->save();
                }
            }

            return $pr;
        });

        $purchaseRequest->load([
            'requesterUser',
            'status',
            'items.supply.measurementUnit',
            'supplyAlerts',
            'purchaseOrders.status',
        ]);

        return response()->json([
            'message' => 'Solicitud de compra registrada exitosamente.',
            'purchase_request' => new PurchaseRequestResource($purchaseRequest),
        ], 201);
    }

    #[OA\Post(
        path: '/api/purchase-requests/{id}/approve',
        operationId: 'approvePurchaseRequest',
        description: 'Aprueba una solicitud de compra, define cantidades y proveedor, y genera automáticamente la orden de compra.',
        summary: 'Aprobar solicitud de compra y generar orden',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la solicitud', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ApprovePurchaseRequestRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Solicitud aprobada y orden de compra generada exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación o solicitud ya procesada.'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada.'),
        ]
    )]
    public function approve(ApprovePurchaseRequestRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->with(['items.supply', 'status'])->find($id);

        if (! $purchaseRequest) {
            return response()->json([
                'message' => 'Solicitud de compra no encontrada.',
            ], 404);
        }

        if ($purchaseRequest->status?->name !== PurchaseRequestStatus::PENDIENTE) {
            return response()->json([
                'message' => 'Solo se pueden aprobar solicitudes en estado pendiente. El estado actual es: ' . ($purchaseRequest->status?->name ?? 'desconocido'),
            ], 422);
        }

        $supplier = Supplier::query()->find($validated['supplier_id']);
        if (! $supplier || ! $supplier->is_active) {
            return response()->json([
                'message' => 'El proveedor seleccionado está inactivo o no existe.',
            ], 422);
        }

        $result = DB::transaction(function () use ($purchaseRequest, $validated, $user, $supplier) {
            $approvedStatus = PurchaseRequestStatus::query()->firstOrCreate(
                ['name' => PurchaseRequestStatus::APROBADA],
                ['name' => PurchaseRequestStatus::APROBADA]
            );

            $orderSolicitadaStatus = PurchaseOrderStatus::query()->firstOrCreate(
                ['name' => PurchaseOrderStatus::SOLICITADA],
                ['name' => PurchaseOrderStatus::SOLICITADA]
            );

            // Generar código único de orden de compra: OC-YYYYMMDD-XXXX
            $datePrefix = now()->format('Ymd');
            $countToday = PurchaseOrder::query()->whereDate('created_at', now()->toDateString())->count() + 1;
            $code = sprintf('OC-%s-%04d', $datePrefix, $countToday);

            $purchaseOrder = PurchaseOrder::query()->create([
                'code' => $code,
                'supplier_id' => $supplier->id,
                'admin_user_id' => $user->id,
                'purchase_request_id' => $purchaseRequest->id,
                'purchase_order_status_id' => $orderSolicitadaStatus->id,
                'total' => 0.00,
                'expected_date' => $validated['expected_date'] ?? null,
            ]);

            $itemsMap = collect($validated['items'] ?? [])->keyBy('supply_id');
            $total = 0.0;

            foreach ($purchaseRequest->items as $prItem) {
                $override = $itemsMap->get($prItem->supply_id);
                $quantity = $override ? (float) $override['quantity'] : (float) $prItem->suggested_quantity;

                // Precio unitario: override -> precio acordado con proveedor -> costo unitario del insumo -> 0
                $unitPrice = 0.0;
                if ($override && isset($override['unit_price'])) {
                    $unitPrice = (float) $override['unit_price'];
                } else {
                    $supplierSupply = DB::table('supplier_supplies')
                        ->where('supplier_id', $supplier->id)
                        ->where('supply_id', $prItem->supply_id)
                        ->first();

                    if ($supplierSupply && $supplierSupply->agreed_price) {
                        $unitPrice = (float) $supplierSupply->agreed_price;
                    } else {
                        $unitPrice = (float) ($prItem->supply?->unit_cost ?? 0.0);
                    }
                }

                $subtotal = round($quantity * $unitPrice, 2);
                $total += $subtotal;

                // Actualizar cantidad aprobada en el ítem de la solicitud
                $prItem->approved_quantity = $quantity;
                $prItem->save();

                // Crear ítem de la orden de compra
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'supply_id' => $prItem->supply_id,
                    'ordered_quantity' => $quantity,
                    'received_quantity' => null,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $purchaseOrder->total = round($total, 2);
            $purchaseOrder->save();

            // Actualizar estado de la solicitud
            $purchaseRequest->purchase_request_status_id = $approvedStatus->id;
            $purchaseRequest->save();

            return [
                'purchase_request' => $purchaseRequest,
                'purchase_order' => $purchaseOrder,
            ];
        });

        $purchaseOrder = $result['purchase_order'];
        $purchaseOrder->load(['supplier', 'adminUser', 'status', 'items.supply.measurementUnit']);
        $purchaseRequest->load(['requesterUser', 'status', 'items.supply.measurementUnit', 'purchaseOrders.status']);

        // Enviar notificación / orden de compra
        try {
            if (! empty($supplier->email)) {
                Notification::route('mail', $supplier->email)->notify(new PurchaseOrderNotification($purchaseOrder));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar correo de orden de compra al proveedor: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Solicitud de compra aprobada y orden de compra ' . $purchaseOrder->code . ' generada exitosamente.',
            'purchase_request' => new PurchaseRequestResource($purchaseRequest),
            'purchase_order' => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    #[OA\Post(
        path: '/api/purchase-requests/{id}/reject',
        operationId: 'rejectPurchaseRequest',
        description: 'Rechaza una solicitud de compra sin generar ninguna orden de compra, cerrando la solicitud.',
        summary: 'Rechazar solicitud de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la solicitud', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/RejectPurchaseRequestRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Solicitud rechazada exitosamente.'),
            new OA\Response(response: 422, description: 'La solicitud no se encuentra en estado pendiente.'),
            new OA\Response(response: 404, description: 'Solicitud no encontrada.'),
        ]
    )]
    public function reject(RejectPurchaseRequestRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $purchaseRequest = PurchaseRequest::query()->with(['status', 'items'])->find($id);

        if (! $purchaseRequest) {
            return response()->json([
                'message' => 'Solicitud de compra no encontrada.',
            ], 404);
        }

        if ($purchaseRequest->status?->name !== PurchaseRequestStatus::PENDIENTE) {
            return response()->json([
                'message' => 'Solo se pueden rechazar solicitudes en estado pendiente. El estado actual es: ' . ($purchaseRequest->status?->name ?? 'desconocido'),
            ], 422);
        }

        $rejectedStatus = PurchaseRequestStatus::query()->firstOrCreate(
            ['name' => PurchaseRequestStatus::RECHAZADA_SIN_COMPRAR],
            ['name' => PurchaseRequestStatus::RECHAZADA_SIN_COMPRAR]
        );

        $purchaseRequest->purchase_request_status_id = $rejectedStatus->id;
        if (! empty($validated['reason'])) {
            $notes = trim((string) $validated['reason']);
            $purchaseRequest->reason = ($purchaseRequest->reason ? $purchaseRequest->reason . ' | Rechazo: ' : 'Rechazo: ') . $notes;
        }
        $purchaseRequest->save();

        $purchaseRequest->load(['requesterUser', 'status', 'items.supply.measurementUnit']);

        return response()->json([
            'message' => 'Solicitud de compra rechazada sin generar compra.',
            'purchase_request' => new PurchaseRequestResource($purchaseRequest),
        ]);
    }
}
