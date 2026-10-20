<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\CreateSupplierRequest;
use App\Http\Requests\Supplier\RecordDeliveryRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\DeliveryIncidentResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\SupplierResource;
use App\Models\DeliveryIncident;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierSupply;
use App\Models\Supply;
use App\Models\User;
use App\Notifications\DeliveryIncidentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class SupplierController extends Controller
{
    #[OA\Get(
        path: '/api/suppliers',
        operationId: 'listSuppliers',
        description: 'Obtiene el listado de proveedores registrados, permitiendo filtrar por término de búsqueda (empresa, contacto, teléfono, correo), estado activo y día de entrega habitual.',
        summary: 'Listar y buscar proveedores',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por razón social, contacto, teléfono o correo', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_active', in: 'query', description: 'Filtrar por estado activo/inactivo', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'delivery_day_id', in: 'query', description: 'Filtrar por ID de día de entrega habitual', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de proveedores obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SupplierResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 10),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->with([
                'deliveryDays',
                'supplierSupplies.supply.measurementUnit',
                'purchaseOrders',
                'deliveryIncidents',
            ])
            ->withCount(['purchaseOrders', 'deliveryIncidents']);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->query('is_active') !== '' && $request->query('is_active') !== null) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($request->filled('delivery_day_id')) {
            $dayId = (int) $request->query('delivery_day_id');
            $query->whereHas('deliveryDays', function ($q) use ($dayId) {
                $q->where('delivery_days.id', $dayId);
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $suppliers = $query->orderBy('company_name')->paginate($perPage);

        return response()->json([
            'data' => SupplierResource::collection($suppliers->items()),
            'current_page' => $suppliers->currentPage(),
            'per_page' => $suppliers->perPage(),
            'total' => $suppliers->total(),
            'last_page' => $suppliers->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/suppliers',
        operationId: 'createSupplier',
        description: 'Registra un nuevo proveedor en el sistema con información de contacto, días de entrega asignados y lista de productos/insumos con precios acordados.',
        summary: 'Registrar un nuevo proveedor',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateSupplierRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Proveedor registrado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/SupplierResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proveedor registrado exitosamente.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Datos incorrectos o incompletos.'),
            new OA\Response(response: 403, description: 'Acceso no autorizado.'),
        ]
    )]
    public function store(CreateSupplierRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $supplier = DB::transaction(function () use ($validated) {
            $supplier = Supplier::create([
                'company_name' => $validated['company_name'],
                'contact_name' => $validated['contact_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (! empty($validated['delivery_day_ids'])) {
                $supplier->deliveryDays()->sync($validated['delivery_day_ids']);
            }

            if (! empty($validated['supplies'])) {
                foreach ($validated['supplies'] as $item) {
                    SupplierSupply::create([
                        'supplier_id' => $supplier->id,
                        'supply_id' => $item['supply_id'],
                        'agreed_price' => $item['agreed_price'],
                    ]);
                }
            }

            return $supplier;
        });

        $supplier->load([
            'deliveryDays',
            'supplierSupplies.supply.measurementUnit',
            'purchaseOrders',
            'deliveryIncidents',
        ]);
        $supplier->loadCount(['purchaseOrders', 'deliveryIncidents']);

        return response()->json([
            'data' => new SupplierResource($supplier),
            'message' => 'Proveedor registrado exitosamente.',
        ], 201);
    }

    #[OA\Get(
        path: '/api/suppliers/{id}',
        operationId: 'getSupplier',
        description: 'Consulta el detalle completo de un proveedor, incluyendo información de contacto, días de entrega, productos con precios pactados, historial de compras e historial de incidencias.',
        summary: 'Consultar información detallada e historial de un proveedor',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del proveedor', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle e historial del proveedor obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/SupplierResource'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Proveedor no encontrado.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::query()
            ->with([
                'deliveryDays',
                'supplierSupplies.supply.measurementUnit',
                'purchaseOrders.status',
                'purchaseOrders.items.supply.measurementUnit',
                'purchaseOrders.deliveryIncidents.type',
                'deliveryIncidents.type',
                'deliveryIncidents.status',
                'deliveryIncidents.receivingUser',
                'deliveryIncidents.purchaseOrder',
            ])
            ->withCount(['purchaseOrders', 'deliveryIncidents'])
            ->findOrFail($id);

        return response()->json([
            'data' => new SupplierResource($supplier),
        ]);
    }

    #[OA\Put(
        path: '/api/suppliers/{id}',
        operationId: 'updateSupplier',
        description: 'Actualiza los datos de contacto, días de entrega y productos/precios pactados con un proveedor.',
        summary: 'Actualizar información de un proveedor',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del proveedor', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateSupplierRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proveedor actualizado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/SupplierResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proveedor actualizado exitosamente.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Datos inválidos.'),
            new OA\Response(response: 404, description: 'Proveedor no encontrado.'),
        ]
    )]
    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        if (! $supplier->is_active) {
            return response()->json([
                'message' => 'No se puede modificar la información de un proveedor inactivo. Debe activarlo primero.',
            ], 422);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($supplier, $validated) {
            $supplier->update(array_filter([
                'company_name' => $validated['company_name'] ?? $supplier->company_name,
                'contact_name' => array_key_exists('contact_name', $validated) ? $validated['contact_name'] : $supplier->contact_name,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $supplier->phone,
                'email' => array_key_exists('email', $validated) ? $validated['email'] : $supplier->email,
                'address' => array_key_exists('address', $validated) ? $validated['address'] : $supplier->address,
                'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : $supplier->is_active,
            ], fn ($val) => $val !== null || true));

            if (array_key_exists('delivery_day_ids', $validated)) {
                $supplier->deliveryDays()->sync($validated['delivery_day_ids'] ?? []);
            }

            if (array_key_exists('supplies', $validated)) {
                SupplierSupply::where('supplier_id', $supplier->id)->delete();
                if (! empty($validated['supplies'])) {
                    foreach ($validated['supplies'] as $item) {
                        SupplierSupply::create([
                            'supplier_id' => $supplier->id,
                            'supply_id' => $item['supply_id'],
                            'agreed_price' => $item['agreed_price'],
                        ]);
                    }
                }
            }
        });

        $supplier->load([
            'deliveryDays',
            'supplierSupplies.supply.measurementUnit',
            'purchaseOrders',
            'deliveryIncidents',
        ]);
        $supplier->loadCount(['purchaseOrders', 'deliveryIncidents']);

        return response()->json([
            'data' => new SupplierResource($supplier),
            'message' => 'Proveedor actualizado exitosamente.',
        ]);
    }

    #[OA\Patch(
        path: '/api/suppliers/{id}/status',
        operationId: 'toggleSupplierStatus',
        description: 'Realiza una baja lógica o reactivación del proveedor, conservando intacto su historial de compras e incidencias.',
        summary: 'Activar o desactivar proveedor (baja lógica)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del proveedor', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado del proveedor actualizado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/SupplierResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Proveedor desactivado exitosamente (baja lógica).'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Proveedor no encontrado.'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = ! $supplier->is_active;
        $supplier->save();

        $supplier->load([
            'deliveryDays',
            'supplierSupplies.supply.measurementUnit',
            'purchaseOrders',
            'deliveryIncidents',
        ]);
        $supplier->loadCount(['purchaseOrders', 'deliveryIncidents']);

        $statusText = $supplier->is_active ? 'activado' : 'desactivado (baja lógica)';

        return response()->json([
            'data' => new SupplierResource($supplier),
            'message' => "Proveedor {$statusText} exitosamente. Su historial se conserva intacto.",
        ]);
    }

    #[OA\Delete(
        path: '/api/suppliers/{id}',
        operationId: 'deleteSupplier',
        description: 'Desactiva lógicamente al proveedor del sistema conservando todo su historial asociado.',
        summary: 'Dar de baja lógica a un proveedor',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del proveedor', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Baja lógica realizada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Proveedor desactivado exitosamente (baja lógica).'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Proveedor no encontrado.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = false;
        $supplier->save();

        return response()->json([
            'message' => 'Proveedor desactivado exitosamente (baja lógica). Su historial de compras e incidencias se mantiene intacto.',
        ]);
    }

    #[OA\Post(
        path: '/api/suppliers/{id}/deliveries',
        operationId: 'recordSupplierDelivery',
        description: 'Registra la recepción de una entrega de proveedor realizada por el personal (mesero/administrador). Si no se detectan problemas, se actualiza el historial como entrega completa. Si se detectan anomalías de peso, calidad o retraso, se registra la incidencia y se actualiza el historial.',
        summary: 'Registrar recepción de entrega (con o sin incidencia)',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del proveedor', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RecordDeliveryRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Entrega procesada y registrada en el historial correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'purchase_order', ref: '#/components/schemas/PurchaseOrderResource'),
                        new OA\Property(property: 'incident', ref: '#/components/schemas/DeliveryIncidentResource', nullable: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Entrega registrada exitosamente sin incidencias.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Datos de validación inválidos.'),
            new OA\Response(response: 404, description: 'Proveedor u orden no encontrada.'),
        ]
    )]
    public function recordDelivery(RecordDeliveryRequest $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        if (! $supplier->is_active) {
            return response()->json([
                'message' => 'No se pueden registrar entregas para un proveedor inactivo. Debe activarlo primero.',
            ], 422);
        }

        $validated = $request->validated();
        $user = Auth::user();

        $result = DB::transaction(function () use ($supplier, $validated, $user) {
            $hasIncident = (bool) $validated['has_incident'];

            // Status 2 = recibida_completa, Status 3 = recibida_con_incidencia
            $targetStatusId = $hasIncident ? 3 : 2;

            $purchaseOrder = null;
            if (! empty($validated['purchase_order_id'])) {
                $purchaseOrder = PurchaseOrder::where('supplier_id', $supplier->id)
                    ->findOrFail($validated['purchase_order_id']);
                $purchaseOrder->purchase_order_status_id = $targetStatusId;
                $purchaseOrder->received_date = now()->toDateString();
                $purchaseOrder->save();
            } else {
                // Generar registro de orden/recepción de entrega
                $uniqueCode = 'ENT-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
                $total = 0.00;

                if (! empty($validated['items'])) {
                    foreach ($validated['items'] as $it) {
                        $qty = (float) $it['received_quantity'];
                        $price = (float) ($it['unit_price'] ?? 0);
                        $total += ($qty * $price);
                    }
                }

                $purchaseOrder = PurchaseOrder::create([
                    'code' => $uniqueCode,
                    'supplier_id' => $supplier->id,
                    'admin_user_id' => $user->id,
                    'purchase_order_status_id' => $targetStatusId,
                    'total' => $total,
                    'expected_date' => now()->toDateString(),
                    'received_date' => now()->toDateString(),
                ]);

                if (! empty($validated['items'])) {
                    foreach ($validated['items'] as $it) {
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $purchaseOrder->id,
                            'supply_id' => $it['supply_id'],
                            'ordered_quantity' => $it['received_quantity'],
                            'received_quantity' => $it['received_quantity'],
                            'unit_price' => $it['unit_price'] ?? 0,
                            'subtotal' => ((float) $it['received_quantity']) * ((float) ($it['unit_price'] ?? 0)),
                        ]);
                    }
                }
            }

            $incident = null;
            if ($hasIncident) {
                // Registrar incidencia asociada al proveedor
                $incident = DeliveryIncident::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'supplier_id' => $supplier->id,
                    'receiving_user_id' => $user->id,
                    'delivery_incident_type_id' => $validated['delivery_incident_type_id'],
                    'delivery_incident_status_id' => 1, // 'reportada'
                    'description' => $validated['description'],
                    'evidence_path' => $validated['evidence_path'] ?? null,
                ]);
            } else {
                // Actualizar existencias e inventario conforme (HU-10 y HU-14)
                $compraEntradaType = InventoryMovementType::firstOrCreate(
                    ['name' => InventoryMovementType::COMPRA_ENTRADA],
                    ['name' => InventoryMovementType::COMPRA_ENTRADA]
                );

                $purchaseOrder->load('items');
                foreach ($purchaseOrder->items as $orderItem) {
                    $supply = Supply::where('id', $orderItem->supply_id)->lockForUpdate()->first();
                    if ($supply) {
                        $prevStock = (float) $supply->current_stock;
                        $qty = (float) ($orderItem->received_quantity ?? $orderItem->ordered_quantity);
                        $newStock = round($prevStock + $qty, 2);
                        $supply->current_stock = $newStock;
                        $supply->save();

                        InventoryMovement::create([
                            'supply_id' => $supply->id,
                            'inventory_movement_type_id' => $compraEntradaType->id,
                            'user_id' => $user->id,
                            'order_id' => null,
                            'order_item_id' => null,
                            'purchase_order_id' => $purchaseOrder->id,
                            'quantity' => $qty,
                            'previous_stock' => $prevStock,
                            'new_stock' => $newStock,
                            'reason' => "Recepción conforme de entrega proveedor {$supplier->company_name} (Orden #{$purchaseOrder->code})",
                        ]);
                    }
                }
            }

            return [
                'purchase_order' => $purchaseOrder,
                'incident' => $incident,
                'has_incident' => $hasIncident,
            ];
        });

        $purchaseOrder = $result['purchase_order'];
        $purchaseOrder->load(['status', 'items.supply.measurementUnit', 'deliveryIncidents.type']);

        $incidentResource = null;
        if ($result['incident']) {
            $result['incident']->load(['type', 'status', 'receivingUser', 'purchaseOrder']);
            $incidentResource = new DeliveryIncidentResource($result['incident']);

            // Notificar por correo a las administradoras activas (Scenario 2)
            try {
                $adminRole = Role::where('name', 'Administrador')->first();
                if ($adminRole) {
                    $admins = User::where('role_id', $adminRole->id)->where('is_active', true)->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new DeliveryIncidentNotification($result['incident']));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar notificación de incidencia a las administradoras: '.$e->getMessage());
            }
        }

        $message = $result['has_incident']
            ? 'Incidencia registrada exitosamente. Se actualizó el historial del proveedor con la incidencia reportada.'
            : 'Entrega registrada exitosamente sin incidencias. Se actualizó el historial del proveedor.';

        return response()->json([
            'purchase_order' => new PurchaseOrderResource($purchaseOrder),
            'incident' => $incidentResource,
            'message' => $message,
        ], $result['has_incident'] ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/suppliers/{id}/history',
        operationId: 'getSupplierHistory',
        description: 'Obtiene el historial de compras/entregas y de incidencias del proveedor especificado.',
        summary: 'Consultar historial de entregas e incidencias de un proveedor',
        security: [['bearerAuth' => []]],
        tags: ['Gestión de Proveedores'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del proveedor', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historial obtenido correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'purchase_orders', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseOrderResource')),
                        new OA\Property(property: 'delivery_incidents', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliveryIncidentResource')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Proveedor no encontrado.'),
        ]
    )]
    public function history(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $orders = PurchaseOrder::query()
            ->where('supplier_id', $supplier->id)
            ->with(['status', 'items.supply.measurementUnit', 'deliveryIncidents.type'])
            ->orderByDesc('id')
            ->get();

        $incidents = DeliveryIncident::query()
            ->where('supplier_id', $supplier->id)
            ->with(['type', 'status', 'receivingUser', 'purchaseOrder'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'purchase_orders' => PurchaseOrderResource::collection($orders),
            'delivery_incidents' => DeliveryIncidentResource::collection($incidents),
        ]);
    }
}
