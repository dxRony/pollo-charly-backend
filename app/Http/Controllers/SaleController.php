<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ComandaEstadoActualizado;
use App\Events\VentaRegistrada;
use App\Http\Requests\Sale\CreateSaleRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Http\Resources\ReceiptTypeResource;
use App\Http\Resources\SaleResource;
use App\Models\AlertOrigin;
use App\Models\AlertStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\Order;
use App\Models\OrderItemStatus;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\ReceiptType;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\TableStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SaleController extends Controller
{
    #[OA\Get(
        path: '/api/sales',
        operationId: 'listSales',
        description: 'Obtiene el listado paginado de ventas registradas en el sistema, permitiendo filtrar por fecha, cajero, método de pago y tipo de comprobante.',
        summary: 'Listar ventas comerciales',
        security: [['bearerAuth' => []]],
        tags: ['Ventas y Facturación'],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', description: 'Filtrar por fecha específica (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'cashier_user_id', in: 'query', description: 'Filtrar por ID del cajero responsable', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'payment_method', in: 'query', description: 'Filtrar por método de pago (efectivo, tarjeta, transferencia)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'receipt_type', in: 'query', description: 'Filtrar por tipo de comprobante (ticket, factura)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por número de comprobante o código de comanda', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de ventas obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SaleResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 25),
                        new OA\Property(property: 'last_page', type: 'integer', example: 2),
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
        $query = Sale::query()->with([
            'order.restaurantTable.status',
            'order.items.dish',
            'cashier.role',
            'receiptType',
            'paymentMethod',
            'status',
        ]);

        if ($request->filled('date')) {
            $query->whereDate('created_at', (string) $request->query('date'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        if ($request->filled('cashier_user_id')) {
            $query->where('cashier_user_id', (int) $request->query('cashier_user_id'));
        }

        if ($request->filled('payment_method')) {
            $query->whereHas('paymentMethod', function ($q) use ($request) {
                $q->where('name', (string) $request->query('payment_method'));
            });
        }

        if ($request->filled('receipt_type')) {
            $query->whereHas('receiptType', function ($q) use ($request) {
                $q->where('name', (string) $request->query('receipt_type'));
            });
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($subQ) use ($search) {
                        $subQ->where('code', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $sales = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => SaleResource::collection($sales->items()),
            'current_page' => $sales->currentPage(),
            'per_page' => $sales->perPage(),
            'total' => $sales->total(),
            'last_page' => $sales->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/sales',
        operationId: 'createSale',
        description: 'Registra la venta a partir de una comanda en estado "lista". Valida el cobro, calcula el vuelto en efectivo, genera el comprobante interno, descuenta el inventario físico (movimiento de salida consumo_venta), marca la comanda como entregada, libera la mesa (si era en mesa) y notifica en tiempo real vía WebSocket.',
        summary: 'Registrar venta y cierre de comanda',
        security: [['bearerAuth' => []]],
        tags: ['Ventas y Facturación'],
        requestBody: new OA\RequestBody(
            description: 'Datos de la venta, método de pago y monto recibido',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateSaleRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Venta registrada exitosamente y comanda cerrada.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Venta registrada exitosamente y comanda cerrada.'),
                        new OA\Property(property: 'sale', ref: '#/components/schemas/SaleResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Comanda no lista, monto recibido insuficiente o comanda ya vendida.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No se puede registrar la venta. La comanda debe estar en estado "lista" para proceder al cobro y cierre.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'No autorizado. Se requiere rol de Mesero/Cajero o Administrador.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para registrar ventas. Se requiere rol de Mesero/Cajero o Administrador.')]
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
    public function store(CreateSaleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $orderId = (int) $validated['order_id'];

        $result = DB::transaction(function () use ($orderId, $validated, $user, $request) {
            $order = Order::query()
                ->with([
                    'status',
                    'restaurantTable.status',
                    'items.dish.recipes.supply',
                    'items.status',
                    'items.complements.complement.complementSupplies.supply',
                ])
                ->lockForUpdate()
                ->find($orderId);

            if (! $order) {
                return response()->json([
                    'message' => 'Comanda no encontrada.',
                ], 404);
            }

            // 1. Validar que no exista una venta previa para esta comanda
            if (Sale::query()->where('order_id', $order->id)->exists()) {
                return response()->json([
                    'message' => 'Esta comanda ya cuenta con una venta registrada previamente.',
                ], 422);
            }

            // 2. Validar que la comanda esté exactamente en estado 'lista'
            if ($order->status?->name !== OrderStatus::LISTA) {
                return response()->json([
                    'message' => "No se puede registrar la venta. La comanda debe estar en estado 'lista' para proceder al cobro y cierre (estado actual: '{$order->status?->name}').",
                ], 422);
            }

            // 3. Calcular subtotal a partir de ítems y complementos activos (excluyendo eliminados y cancelados)
            $subtotal = 0.00;
            $activeItems = $order->items->filter(function ($item) {
                $statusName = $item->status?->name;
                return ! in_array($statusName, [OrderItemStatus::ELIMINADO, OrderItemStatus::CANCELADO], true);
            });

            if ($activeItems->isEmpty()) {
                return response()->json([
                    'message' => 'La comanda no posee platillos activos para facturar.',
                ], 422);
            }

            foreach ($activeItems as $item) {
                $itemSubtotal = (float) $item->subtotal;
                $complementsSubtotal = $item->complements
                    ? (float) $item->complements->sum('subtotal')
                    : 0.00;

                $subtotal += ($itemSubtotal + $complementsSubtotal);
            }

            $subtotal = round($subtotal, 2);
            $discount = round((float) ($validated['discount'] ?? 0.00), 2);
            $tax = round((float) ($validated['tax'] ?? 0.00), 2);
            $total = round(max(0.00, $subtotal - $discount + $tax), 2);

            // 4. Resolver método de pago y calcular vuelto
            $paymentMethodName = $request->getResolvedPaymentMethodName();
            $paymentMethod = PaymentMethod::query()->where('name', $paymentMethodName)->firstOrFail();

            $receiptTypeName = $request->getResolvedReceiptTypeName();
            $receiptType = ReceiptType::query()->where('name', $receiptTypeName)->firstOrFail();

            if ($paymentMethodName === PaymentMethod::EFECTIVO) {
                $receivedAmount = round((float) $validated['received_amount'], 2);

                if ($receivedAmount < $total) {
                    return response()->json([
                        'message' => "El monto recibido (Q" . number_format($receivedAmount, 2) . ") es menor al total a pagar (Q" . number_format($total, 2) . ").",
                    ], 422);
                }

                $changeAmount = round($receivedAmount - $total, 2);
            } else {
                // Pagos con tarjeta o transferencia: recibido igual al total, vuelto cero
                $receivedAmount = $total;
                $changeAmount = 0.00;
            }

            // 5. Deducir inventario real (convertir reserva en salida definitiva de tipo consumo_venta)
            $suppliesNeeded = [];

            foreach ($activeItems as $item) {
                $itemQty = (int) $item->quantity;

                foreach ($item->dish->recipes as $recipe) {
                    $sId = $recipe->supply_id;
                    $needed = (float) $recipe->required_quantity * $itemQty;
                    $suppliesNeeded[$sId] = ($suppliesNeeded[$sId] ?? 0.0) + $needed;
                }

                foreach ($item->complements as $itemComp) {
                    $compQty = (int) $itemComp->quantity;
                    foreach ($itemComp->complement->complementSupplies as $compSupply) {
                        $sId = $compSupply->supply_id;
                        $needed = (float) $compSupply->required_quantity * $compQty;
                        $suppliesNeeded[$sId] = ($suppliesNeeded[$sId] ?? 0.0) + $needed;
                    }
                }
            }

            $consumoVentaType = InventoryMovementType::query()
                ->where('name', InventoryMovementType::CONSUMO_VENTA)
                ->firstOrFail();

            foreach ($suppliesNeeded as $supplyId => $amountToDeduct) {
                $supply = Supply::query()->lockForUpdate()->findOrFail($supplyId);
                $previousStock = (float) $supply->current_stock;
                $deductQty = round($amountToDeduct, 2);
                $newStock = round(max(0.00, $previousStock - $deductQty), 2);

                $supply->current_stock = $newStock;
                $supply->save();

                InventoryMovement::query()->create([
                    'supply_id' => $supply->id,
                    'inventory_movement_type_id' => $consumoVentaType->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'quantity' => $deductQty,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => "Consumo de inventario por venta de comanda {$order->code}",
                ]);

                $this->checkAndCreateLowStockAlert($supply, $user);
            }

            // 6. Generar correlativo único diario para el comprobante de venta
            $today = now()->format('Ymd');
            $countToday = Sale::query()->whereDate('created_at', now()->toDateString())->count();
            $receiptNumber = sprintf('VTA-%s-%04d', $today, $countToday + 1);
            while (Sale::query()->where('receipt_number', $receiptNumber)->exists()) {
                $countToday++;
                $receiptNumber = sprintf('VTA-%s-%04d', $today, $countToday + 1);
            }

            // 7. Crear el registro de venta
            $completedStatus = SaleStatus::query()->where('name', SaleStatus::COMPLETADA)->firstOrFail();

            $sale = Sale::query()->create([
                'order_id' => $order->id,
                'cashier_user_id' => $user->id,
                'receipt_type_id' => $receiptType->id,
                'payment_method_id' => $paymentMethod->id,
                'sale_status_id' => $completedStatus->id,
                'receipt_number' => $receiptNumber,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
            ]);

            // 8. Marcar la comanda como "entregada"
            $deliveredStatus = OrderStatus::query()->where('name', OrderStatus::ENTREGADA)->firstOrFail();
            $order->order_status_id = $deliveredStatus->id;
            $order->save();

            // 9. Liberar la mesa asociada si era comanda de tipo en mesa
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::query()->lockForUpdate()->find($order->restaurant_table_id);
                if ($table) {
                    $availableTableStatus = TableStatus::query()->where('name', TableStatus::DISPONIBLE)->firstOrFail();
                    $table->table_status_id = $availableTableStatus->id;
                    $table->save();
                }
            }

            return [
                'sale' => $sale,
                'order' => $order,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $sale = $result['sale'];
        $order = $result['order'];

        $sale->load([
            'order.restaurantTable.status',
            'order.items.dish',
            'cashier.role',
            'receiptType',
            'paymentMethod',
            'status',
        ]);

        $order->load([
            'restaurantTable.status',
            'waiter.role',
            'type',
            'status',
            'items.dish.category',
            'items.status',
            'items.complements.complement',
        ]);

        // 10. Transmitir eventos en tiempo real a cocina y sala vía WebSockets
        broadcast(new VentaRegistrada($sale));
        broadcast(new ComandaEstadoActualizado($order, OrderStatus::LISTA, OrderStatus::ENTREGADA));

        return response()->json([
            'message' => 'Venta registrada exitosamente y comanda cerrada.',
            'sale' => new SaleResource($sale),
        ], 201);
    }

    #[OA\Get(
        path: '/api/sales/{id}',
        operationId: 'getSale',
        description: 'Obtiene el detalle completo de una venta con su comanda, líneas de consumo, método de pago y comprobante.',
        summary: 'Consultar detalle de una venta',
        security: [['bearerAuth' => []]],
        tags: ['Ventas y Facturación'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la venta', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle de la venta obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'sale', ref: '#/components/schemas/SaleResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Venta no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Venta no encontrada.')]
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
    public function show(int $id): JsonResponse
    {
        $sale = Sale::query()->with([
            'order.restaurantTable.status',
            'order.items.dish',
            'cashier.role',
            'receiptType',
            'paymentMethod',
            'status',
        ])->find($id);

        if (! $sale) {
            return response()->json([
                'message' => 'Venta no encontrada.',
            ], 404);
        }

        return response()->json([
            'sale' => new SaleResource($sale),
        ]);
    }

    #[OA\Get(
        path: '/api/payment-methods',
        operationId: 'listPaymentMethods',
        description: 'Obtiene el catálogo de métodos de pago disponibles (efectivo, tarjeta, transferencia).',
        summary: 'Listar métodos de pago',
        security: [['bearerAuth' => []]],
        tags: ['Ventas y Facturación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de métodos de pago obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaymentMethodResource')),
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
    public function paymentMethods(): JsonResponse
    {
        $methods = PaymentMethod::query()->orderBy('id')->get();

        return response()->json([
            'data' => PaymentMethodResource::collection($methods),
        ]);
    }

    #[OA\Get(
        path: '/api/receipt-types',
        operationId: 'listReceiptTypes',
        description: 'Obtiene el catálogo de tipos de comprobante emitibles (ticket, factura).',
        summary: 'Listar tipos de comprobante',
        security: [['bearerAuth' => []]],
        tags: ['Ventas y Facturación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de tipos de comprobante obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ReceiptTypeResource')),
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
    public function receiptTypes(): JsonResponse
    {
        $types = ReceiptType::query()->orderBy('id')->get();

        return response()->json([
            'data' => ReceiptTypeResource::collection($types),
        ]);
    }

    /**
     * Evalúa el nivel de existencias y genera alerta automática si queda por debajo del mínimo.
     */
    protected function checkAndCreateLowStockAlert(Supply $supply, User $user): void
    {
        if ((float) $supply->current_stock < (float) $supply->minimum_stock) {
            $automaticOrigin = AlertOrigin::query()->firstOrCreate(
                ['name' => AlertOrigin::AUTOMATIC],
                ['name' => AlertOrigin::AUTOMATIC]
            );

            $pendingStatus = AlertStatus::query()->firstOrCreate(
                ['name' => AlertStatus::PENDING],
                ['name' => AlertStatus::PENDING]
            );

            $hasPendingAlert = SupplyAlert::query()
                ->where('supply_id', $supply->id)
                ->where('alert_status_id', $pendingStatus->id)
                ->exists();

            if (! $hasPendingAlert) {
                SupplyAlert::query()->create([
                    'supply_id' => $supply->id,
                    'alert_origin_id' => $automaticOrigin->id,
                    'alert_status_id' => $pendingStatus->id,
                    'user_id' => $user->id,
                    'notes' => 'Alerta automática generada por consumo en venta al quedar la existencia por debajo del mínimo de referencia (' . $supply->minimum_stock . '). Existencia actual: ' . $supply->current_stock,
                ]);
            }
        }
    }
}
