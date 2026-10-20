<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\CreatePurchaseOrderRequest;
use App\Http\Requests\Purchase\ReceivePurchaseOrderRequest;
use App\Http\Requests\Purchase\ReportOrderIncidentRequest;
use App\Http\Resources\DeliveryIncidentResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\DeliveryIncident;
use App\Models\DeliveryIncidentStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestStatus;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Supply;
use App\Models\User;
use App\Notifications\DeliveryIncidentNotification;
use App\Notifications\PurchaseOrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use OpenApi\Attributes as OA;

class PurchaseOrderController extends Controller
{
    #[OA\Get(
        path: '/api/purchase-orders',
        operationId: 'listPurchaseOrders',
        description: 'Obtiene el listado paginado de órdenes y compras a proveedores, con filtros por estado, proveedor, rango de fechas y texto.',
        summary: 'Listar órdenes de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por nombre de estado (solicitada, recibida_completa, recibida_con_incidencia, cancelada)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'purchase_order_status_id', in: 'query', description: 'Filtrar por ID del estado', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'supplier_id', in: 'query', description: 'Filtrar por ID del proveedor', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por código de orden o nombre de proveedor', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de órdenes obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseOrderResource')),
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
        $query = PurchaseOrder::query()->with([
            'supplier',
            'adminUser',
            'status',
            'items.supply.measurementUnit',
            'deliveryIncidents',
        ]);

        if ($request->filled('status')) {
            $statusName = (string) $request->query('status');
            $query->whereHas('status', fn ($q) => $q->where('name', $statusName));
        } elseif ($request->filled('purchase_order_status_id')) {
            $query->where('purchase_order_status_id', (int) $request->query('purchase_order_status_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->query('supplier_id'));
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
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('company_name', 'like', "%{$search}%"));
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $paginated = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => PurchaseOrderResource::collection($paginated->items()),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    #[OA\Get(
        path: '/api/purchase-orders/statuses',
        operationId: 'listPurchaseOrderStatuses',
        description: 'Obtiene el catálogo de estados disponibles para órdenes de compra.',
        summary: 'Listar estados de órdenes de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de estados obtenido exitosamente.'),
        ]
    )]
    public function statuses(): JsonResponse
    {
        $statuses = PurchaseOrderStatus::query()->get();

        return response()->json([
            'data' => $statuses,
        ]);
    }

    #[OA\Get(
        path: '/api/purchase-orders/{id}',
        operationId: 'getPurchaseOrder',
        description: 'Obtiene el detalle completo de una orden de compra, sus productos, cantidades, total e incidencias asociadas.',
        summary: 'Consultar detalle de orden de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la orden', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle de la orden obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'purchase_order', ref: '#/components/schemas/PurchaseOrderResource')]
                )
            ),
            new OA\Response(response: 404, description: 'Orden no encontrada.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $order = PurchaseOrder::query()->with([
            'supplier',
            'adminUser',
            'status',
            'items.supply.measurementUnit',
            'deliveryIncidents',
            'inventoryMovements.supply',
        ])->find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Orden de compra no encontrada.',
            ], 404);
        }

        return response()->json([
            'purchase_order' => new PurchaseOrderResource($order),
        ]);
    }

    #[OA\Post(
        path: '/api/purchase-orders',
        operationId: 'createPurchaseOrder',
        description: 'Registra una compra manual directa a un proveedor sin necesidad de alerta previa, calculando automáticamente subtotales y total.',
        summary: 'Registrar compra manual',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreatePurchaseOrderRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compra registrada exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
        ]
    )]
    public function store(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $supplier = Supplier::query()->find($validated['supplier_id']);
        if (! $supplier || ! $supplier->is_active) {
            return response()->json([
                'message' => 'El proveedor seleccionado está inactivo o no existe.',
            ], 422);
        }

        $purchaseOrder = DB::transaction(function () use ($validated, $user, $supplier) {
            $solicitadaStatus = PurchaseOrderStatus::query()->firstOrCreate(
                ['name' => PurchaseOrderStatus::SOLICITADA],
                ['name' => PurchaseOrderStatus::SOLICITADA]
            );

            // Código único de orden de compra: OC-YYYYMMDD-XXXX
            $datePrefix = now()->format('Ymd');
            $countToday = PurchaseOrder::query()->whereDate('created_at', now()->toDateString())->count() + 1;
            $code = sprintf('OC-%s-%04d', $datePrefix, $countToday);

            $order = PurchaseOrder::query()->create([
                'code' => $code,
                'supplier_id' => $supplier->id,
                'admin_user_id' => $user->id,
                'purchase_request_id' => $validated['purchase_request_id'] ?? null,
                'purchase_order_status_id' => $solicitadaStatus->id,
                'total' => 0.00,
                'expected_date' => $validated['expected_date'] ?? null,
            ]);

            $total = 0.0;

            foreach ($validated['items'] as $itemData) {
                $supply = Supply::query()->find($itemData['supply_id']);
                $qty = (float) $itemData['ordered_quantity'];

                // Determinar precio unitario
                $unitPrice = 0.0;
                if (isset($itemData['unit_price']) && $itemData['unit_price'] !== null) {
                    $unitPrice = (float) $itemData['unit_price'];
                } else {
                    $supplierSupply = DB::table('supplier_supplies')
                        ->where('supplier_id', $supplier->id)
                        ->where('supply_id', $supply->id)
                        ->first();

                    if ($supplierSupply && $supplierSupply->agreed_price) {
                        $unitPrice = (float) $supplierSupply->agreed_price;
                    } else {
                        $unitPrice = (float) ($supply?->unit_cost ?? 0.0);
                    }
                }

                $subtotal = round($qty * $unitPrice, 2);
                $total += $subtotal;

                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $order->id,
                    'supply_id' => $supply->id,
                    'ordered_quantity' => $qty,
                    'received_quantity' => null,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->total = round($total, 2);
            $order->save();

            // Si vino vinculada a una solicitud de compra, marcarla aprobada
            if (! empty($validated['purchase_request_id'])) {
                $pr = PurchaseRequest::query()->find($validated['purchase_request_id']);
                if ($pr) {
                    $approvedStatus = PurchaseRequestStatus::query()->firstOrCreate(
                        ['name' => PurchaseRequestStatus::APROBADA],
                        ['name' => PurchaseRequestStatus::APROBADA]
                    );
                    $pr->purchase_request_status_id = $approvedStatus->id;
                    $pr->save();
                }
            }

            return $order;
        });

        $purchaseOrder->load(['supplier', 'adminUser', 'status', 'items.supply.measurementUnit']);

        // Enviar notificación al proveedor
        try {
            if (! empty($supplier->email)) {
                Notification::route('mail', $supplier->email)->notify(new PurchaseOrderNotification($purchaseOrder));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar notificación de orden de compra al proveedor: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Compra registrada exitosamente con código '.$purchaseOrder->code.'.',
            'purchase_order' => new PurchaseOrderResource($purchaseOrder),
        ], 201);
    }

    #[OA\Post(
        path: '/api/purchase-orders/{id}/receive',
        operationId: 'receivePurchaseOrder',
        description: 'Confirma la recepción de una compra entregada conforme, actualiza el stock del almacén y registra el movimiento de inventario correspondiente.',
        summary: 'Confirmar recepción de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la orden', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ReceivePurchaseOrderRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Recepción confirmada e inventario actualizado exitosamente.'),
            new OA\Response(response: 422, description: 'La orden no se encuentra en estado solicitada.'),
            new OA\Response(response: 404, description: 'Orden no encontrada.'),
        ]
    )]
    public function receive(ReceivePurchaseOrderRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $order = PurchaseOrder::query()->with(['items.supply', 'status'])->find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Orden de compra no encontrada.',
            ], 404);
        }

        $allowedStatuses = [
            PurchaseOrderStatus::SOLICITADA,
            PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA,
        ];

        if (! in_array($order->status?->name, $allowedStatuses, true)) {
            return response()->json([
                'message' => 'Solo se pueden recibir órdenes en estado solicitada o con incidencia en corrección. El estado actual es: '.($order->status?->name ?? 'desconocido'),
            ], 422);
        }

        $result = DB::transaction(function () use ($order, $validated, $user) {
            $recibidaStatus = PurchaseOrderStatus::query()->firstOrCreate(
                ['name' => PurchaseOrderStatus::RECIBIDA_COMPLETA],
                ['name' => PurchaseOrderStatus::RECIBIDA_COMPLETA]
            );

            $compraEntradaType = InventoryMovementType::query()->firstOrCreate(
                ['name' => InventoryMovementType::COMPRA_ENTRADA],
                ['name' => InventoryMovementType::COMPRA_ENTRADA]
            );

            $receivedDate = $validated['received_date'] ?? now()->toDateString();
            $order->received_date = $receivedDate;
            $order->purchase_order_status_id = $recibidaStatus->id;
            $order->save();

            $itemsMap = collect($validated['items'] ?? [])->keyBy('supply_id');

            foreach ($order->items as $item) {
                $override = $itemsMap->get($item->supply_id);
                $qty = $override ? (float) $override['received_quantity'] : (float) $item->ordered_quantity;

                $item->received_quantity = $qty;
                $item->save();

                // Actualizar stock del insumo
                $supply = Supply::query()->where('id', $item->supply_id)->lockForUpdate()->firstOrFail();
                $previousStock = (float) $supply->current_stock;
                $newStock = round($previousStock + $qty, 2);
                $supply->current_stock = $newStock;
                $supply->save();

                // Registrar movimiento de inventario tipo compra_entrada (HU-10)
                InventoryMovement::query()->create([
                    'supply_id' => $supply->id,
                    'inventory_movement_type_id' => $compraEntradaType->id,
                    'user_id' => $user->id,
                    'order_id' => null,
                    'order_item_id' => null,
                    'purchase_order_id' => $order->id,
                    'quantity' => $qty,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => "Recepción conforme de compra orden #{$order->code}",
                ]);
            }

            // Si la orden tenía incidencias abiertas, resolverlas (Scenario 3: Proveedor corrige o repone productos)
            $resolvedStatus = DeliveryIncidentStatus::query()->firstOrCreate(
                ['name' => 'resuelta'],
                ['name' => 'resuelta']
            );

            DeliveryIncident::query()
                ->where('purchase_order_id', $order->id)
                ->where('delivery_incident_status_id', '!=', $resolvedStatus->id)
                ->update(['delivery_incident_status_id' => $resolvedStatus->id]);

            return $order;
        });

        $result->load([
            'supplier',
            'adminUser',
            'status',
            'items.supply.measurementUnit',
            'deliveryIncidents',
            'inventoryMovements.supply',
        ]);

        return response()->json([
            'message' => 'Recepción de compra confirmada exitosamente. Se actualizaron las existencias y el historial de movimientos de inventario.',
            'purchase_order' => new PurchaseOrderResource($result),
        ]);
    }

    #[OA\Post(
        path: '/api/purchase-orders/{id}/incident',
        operationId: 'reportPurchaseOrderIncident',
        description: 'Registra una incidencia de entrega no conforme asociada a una orden de compra y notifica a las administradoras.',
        summary: 'Reportar incidencia en orden de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la orden de compra', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ReportOrderIncidentRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Incidencia reportada exitosamente.'),
            new OA\Response(response: 422, description: 'Error de validación.'),
            new OA\Response(response: 404, description: 'Orden no encontrada.'),
        ]
    )]
    public function reportIncident(ReportOrderIncidentRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $order = PurchaseOrder::query()->with(['supplier', 'status'])->find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Orden de compra no encontrada.',
            ], 404);
        }

        $result = DB::transaction(function () use ($order, $validated, $user) {
            $incidentStatusReportada = DeliveryIncidentStatus::query()->firstOrCreate(
                ['name' => 'reportada'],
                ['name' => 'reportada']
            );

            $orderStatusIncident = PurchaseOrderStatus::query()->firstOrCreate(
                ['name' => PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA],
                ['name' => PurchaseOrderStatus::RECIBIDA_CON_INCIDENCIA]
            );

            $incident = DeliveryIncident::create([
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'receiving_user_id' => $user->id,
                'delivery_incident_type_id' => $validated['delivery_incident_type_id'],
                'delivery_incident_status_id' => $incidentStatusReportada->id,
                'description' => $validated['description'],
                'evidence_path' => $validated['evidence_path'] ?? null,
            ]);

            $order->purchase_order_status_id = $orderStatusIncident->id;
            $order->received_date = now()->toDateString();
            $order->save();

            return $incident;
        });

        $result->load(['supplier', 'receivingUser', 'type', 'status', 'purchaseOrder']);
        $order->load(['supplier', 'adminUser', 'status', 'items.supply.measurementUnit', 'deliveryIncidents']);

        // Notificar por correo a las administradoras activas (Scenario 2)
        try {
            $adminRole = Role::where('name', 'Administrador')->first();
            if ($adminRole) {
                $admins = User::where('role_id', $adminRole->id)->where('is_active', true)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new DeliveryIncidentNotification($result));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar notificación de incidencia a las administradoras: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Incidencia de entrega registrada exitosamente. Se notificó a la administradora para coordinar con el proveedor.',
            'purchase_order' => new PurchaseOrderResource($order),
            'incident' => new DeliveryIncidentResource($result),
        ], 201);
    }

    #[OA\Post(
        path: '/api/purchase-orders/{id}/cancel',
        operationId: 'cancelPurchaseOrder',
        description: 'Cancela una orden de compra pendiente de entrega.',
        summary: 'Cancelar orden de compra',
        security: [['bearerAuth' => []]],
        tags: ['Compras y Abastecimiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la orden', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Orden cancelada exitosamente.'),
            new OA\Response(response: 422, description: 'La orden no se puede cancelar en su estado actual.'),
            new OA\Response(response: 404, description: 'Orden no encontrada.'),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = PurchaseOrder::query()->with(['status', 'items'])->find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Orden de compra no encontrada.',
            ], 404);
        }

        if ($order->status?->name !== PurchaseOrderStatus::SOLICITADA) {
            return response()->json([
                'message' => 'Solo se pueden cancelar órdenes en estado solicitada. El estado actual es: '.($order->status?->name ?? 'desconocido'),
            ], 422);
        }

        $canceladaStatus = PurchaseOrderStatus::query()->firstOrCreate(
            ['name' => PurchaseOrderStatus::CANCELADA],
            ['name' => PurchaseOrderStatus::CANCELADA]
        );

        $order->purchase_order_status_id = $canceladaStatus->id;
        $order->save();

        $order->load(['supplier', 'adminUser', 'status', 'items.supply.measurementUnit']);

        return response()->json([
            'message' => 'Orden de compra cancelada exitosamente.',
            'purchase_order' => new PurchaseOrderResource($order),
        ]);
    }
}
