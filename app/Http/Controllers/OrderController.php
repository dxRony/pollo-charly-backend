<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ComandaCancelada;
use App\Events\ComandaEnviadaACocina;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusResource;
use App\Http\Resources\OrderTypeResource;
use App\Http\Resources\RestaurantTableResource;
use App\Models\Complement;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemComplement;
use App\Models\OrderItemStatus;
use App\Models\OrderStatus;
use App\Models\OrderType;
use App\Models\RestaurantTable;
use App\Models\Supply;
use App\Models\TableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    #[OA\Get(
        path: '/api/orders',
        operationId: 'listOrders',
        description: 'Obtiene el listado paginado de comandas registradas en el sistema, con filtros por estado, tipo de pedido, mesa, mesero y fecha.',
        summary: 'Listar comandas',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por nombre de estado o lista separada por comas (ej. pendiente,en_preparacion)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'order_type', in: 'query', description: 'Filtrar por tipo de pedido (en_mesa o para_llevar)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'restaurant_table_id', in: 'query', description: 'Filtrar por ID de mesa', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'waiter_user_id', in: 'query', description: 'Filtrar por ID de mesero responsable', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date', in: 'query', description: 'Filtrar por fecha específica (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por código de comanda o notas', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de comandas obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderResource')),
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
        $query = Order::query()->with([
            'restaurantTable.status',
            'waiter.role',
            'type',
            'status',
            'items.dish.category',
            'items.status',
            'items.complements.complement',
        ]);

        // Filtro por estado (admite múltiples valores separados por coma, ej: pendiente,en_preparacion)
        if ($request->filled('status')) {
            $statuses = explode(',', (string) $request->query('status'));
            $query->whereHas('status', function ($q) use ($statuses) {
                $q->whereIn('name', array_map('trim', $statuses));
            });
        }

        // Filtro por tipo de pedido
        if ($request->filled('order_type')) {
            $query->whereHas('type', function ($q) use ($request) {
                $q->where('name', (string) $request->query('order_type'));
            });
        }

        // Filtro por mesa
        if ($request->filled('restaurant_table_id')) {
            $query->where('restaurant_table_id', (int) $request->query('restaurant_table_id'));
        }

        // Filtro por mesero
        if ($request->filled('waiter_user_id')) {
            $query->where('waiter_user_id', (int) $request->query('waiter_user_id'));
        }

        // Filtro por fecha
        if ($request->filled('date')) {
            $query->whereDate('created_at', (string) $request->query('date'));
        }

        // Búsqueda por código o notas
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $orders = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/orders',
        operationId: 'createOrder',
        description: 'Registra una nueva comanda (en mesa o para llevar), calculando automáticamente subtotales y totales. Valida que la mesa esté libre (si es en mesa), comprueba que haya existencias suficientes de insumos según recetas, bloquea (reserva) esas existencias y transmite la comanda a cocina en tiempo real vía WebSocket (evento ComandaEnviadaACocina en canal private-cocina).',
        summary: 'Registrar comanda con verificación de insumos y bloqueo de existencias',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        requestBody: new OA\RequestBody(
            description: 'Datos de la comanda y platillos solicitados',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateOrderRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Comanda registrada exitosamente y enviada a cocina.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Comanda registrada y enviada a cocina exitosamente.'),
                        new OA\Property(property: 'order', ref: '#/components/schemas/OrderResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Mesa ocupada, producto no disponible o existencias insuficientes.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No hay suficientes insumos disponibles para preparar los productos solicitados.'),
                        new OA\Property(property: 'insufficient_supplies', type: 'array', items: new OA\Items(type: 'object')),
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
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $orderTypeName = $request->getResolvedOrderTypeName();
        $orderType = OrderType::query()->where('name', $orderTypeName)->firstOrFail();

        // Ejecución transaccional completa con bloqueos pesimistas para concurrencia
        $order = DB::transaction(function () use ($validated, $user, $orderTypeName, $orderType) {
            $table = null;

            // 1. Si es pedido en mesa, validar y bloquear la mesa
            if ($orderTypeName === OrderType::EN_MESA) {
                $table = RestaurantTable::query()
                    ->with('status')
                    ->lockForUpdate()
                    ->findOrFail($validated['restaurant_table_id']);

                if ($table->status?->name !== TableStatus::DISPONIBLE) {
                    return response()->json([
                        'message' => "La mesa #{$table->number} no está disponible actualmente (estado: {$table->status?->name}). Por favor elija otra mesa libre.",
                    ], 422);
                }
            }

            // 2. Pre-cargar platillos y complementos con sus recetas y validar que estén activos
            $itemsData = [];
            $suppliesNeeded = [];

            foreach ($validated['items'] as $rawItem) {
                $dish = Dish::query()
                    ->with(['recipes.supply.measurementUnit'])
                    ->lockForUpdate()
                    ->findOrFail($rawItem['dish_id']);

                if (! $dish->is_active) {
                    return response()->json([
                        'message' => "El platillo '{$dish->name}' no está disponible actualmente.",
                    ], 422);
                }

                $quantity = (int) $rawItem['quantity'];

                // Calcular insumos requeridos por el platillo
                foreach ($dish->recipes as $recipe) {
                    $supplyId = $recipe->supply_id;
                    $neededAmount = (float) $recipe->required_quantity * $quantity;

                    if (! isset($suppliesNeeded[$supplyId])) {
                        $suppliesNeeded[$supplyId] = [
                            'required' => 0.0,
                            'supply' => $recipe->supply,
                        ];
                    }
                    $suppliesNeeded[$supplyId]['required'] += $neededAmount;
                }

                $complementsData = [];
                if (! empty($rawItem['complements'])) {
                    foreach ($rawItem['complements'] as $rawComp) {
                        $complement = Complement::query()
                            ->with(['complementSupplies.supply.measurementUnit'])
                            ->lockForUpdate()
                            ->findOrFail($rawComp['complement_id']);

                        if (! $complement->is_active) {
                            return response()->json([
                                'message' => "El complemento '{$complement->name}' no está disponible actualmente.",
                            ], 422);
                        }

                        $compQuantity = (int) $rawComp['quantity'];

                        // Calcular insumos requeridos por el complemento
                        foreach ($complement->complementSupplies as $compSupply) {
                            $supplyId = $compSupply->supply_id;
                            $neededAmount = (float) $compSupply->required_quantity * $compQuantity;

                            if (! isset($suppliesNeeded[$supplyId])) {
                                $suppliesNeeded[$supplyId] = [
                                    'required' => 0.0,
                                    'supply' => $compSupply->supply,
                                ];
                            }
                            $suppliesNeeded[$supplyId]['required'] += $neededAmount;
                        }

                        $complementsData[] = [
                            'complement' => $complement,
                            'quantity' => $compQuantity,
                        ];
                    }
                }

                $itemsData[] = [
                    'dish' => $dish,
                    'quantity' => $quantity,
                    'notes' => $rawItem['notes'] ?? null,
                    'complements' => $complementsData,
                ];
            }

            // 3. Verificar disponibilidad de insumos considerando existencias actuales y reservas activas
            $insufficientSupplies = [];

            foreach ($suppliesNeeded as $supplyId => $data) {
                $supply = Supply::query()
                    ->with('measurementUnit')
                    ->lockForUpdate()
                    ->findOrFail($supplyId);

                $needed = (float) $data['required'];

                // Calcular existencias ya reservadas por comandas activas
                $activeOrderStatuses = [OrderStatus::PENDIENTE, OrderStatus::EN_PREPARACION, OrderStatus::LISTA];
                $nonCancelledItemStatuses = [OrderItemStatus::CANCELADO, OrderItemStatus::ELIMINADO];

                $reservedDishes = (float) DB::table('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('order_statuses', 'orders.order_status_id', '=', 'order_statuses.id')
                    ->join('order_item_statuses', 'order_items.order_item_status_id', '=', 'order_item_statuses.id')
                    ->join('dish_recipes', 'order_items.dish_id', '=', 'dish_recipes.dish_id')
                    ->whereIn('order_statuses.name', $activeOrderStatuses)
                    ->whereNotIn('order_item_statuses.name', $nonCancelledItemStatuses)
                    ->where('dish_recipes.supply_id', $supplyId)
                    ->sum(DB::raw('order_items.quantity * dish_recipes.required_quantity'));

                $reservedComplements = (float) DB::table('order_item_complements')
                    ->join('order_items', 'order_item_complements.order_item_id', '=', 'order_items.id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('order_statuses', 'orders.order_status_id', '=', 'order_statuses.id')
                    ->join('order_item_statuses', 'order_items.order_item_status_id', '=', 'order_item_statuses.id')
                    ->join('complement_supplies', 'order_item_complements.complement_id', '=', 'complement_supplies.complement_id')
                    ->whereIn('order_statuses.name', $activeOrderStatuses)
                    ->whereNotIn('order_item_statuses.name', $nonCancelledItemStatuses)
                    ->where('complement_supplies.supply_id', $supplyId)
                    ->sum(DB::raw('order_item_complements.quantity * complement_supplies.required_quantity'));

                $totalReserved = round($reservedDishes + $reservedComplements, 2);
                $availableStock = max(0.00, round((float) $supply->current_stock - $totalReserved, 2));

                if ($needed > $availableStock) {
                    $insufficientSupplies[] = [
                        'supply_id' => $supply->id,
                        'name' => $supply->name,
                        'required' => round($needed, 2),
                        'available' => round($availableStock, 2),
                        'current_stock' => (float) $supply->current_stock,
                        'reserved_stock' => $totalReserved,
                        'unit' => $supply->measurementUnit?->abbreviation ?? $supply->measurementUnit?->name ?? '',
                    ];
                }
            }

            if (count($insufficientSupplies) > 0) {
                return response()->json([
                    'message' => 'No hay suficientes insumos disponibles para preparar los productos solicitados.',
                    'insufficient_supplies' => $insufficientSupplies,
                ], 422);
            }

            // 4. Generar código único para la comanda
            $today = now()->format('Ymd');
            $countToday = Order::query()->whereDate('created_at', now()->toDateString())->count();
            $code = sprintf('COM-%s-%04d', $today, $countToday + 1);
            while (Order::query()->where('code', $code)->exists()) {
                $countToday++;
                $code = sprintf('COM-%s-%04d', $today, $countToday + 1);
            }

            $pendingStatus = OrderStatus::query()->where('name', OrderStatus::PENDIENTE)->firstOrFail();
            $pendingItemStatus = OrderItemStatus::query()->where('name', OrderItemStatus::PENDIENTE)->firstOrFail();

            // 5. Crear la comanda
            $newOrder = Order::query()->create([
                'code' => $code,
                'restaurant_table_id' => $table?->id,
                'waiter_user_id' => $user->id,
                'order_type_id' => $orderType->id,
                'order_status_id' => $pendingStatus->id,
                'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            ]);

            // 6. Si es en mesa, marcar la mesa como ocupada dentro de la misma transacción
            if ($table) {
                $occupiedStatus = TableStatus::query()->where('name', TableStatus::OCUPADA)->firstOrFail();
                $table->table_status_id = $occupiedStatus->id;
                $table->save();
            }

            // 7. Guardar ítems y complementos con cálculo automático de subtotal
            foreach ($itemsData as $data) {
                $dish = $data['dish'];
                $quantity = $data['quantity'];
                $unitPrice = (float) $dish->price;
                $subtotal = round($unitPrice * $quantity, 2);

                $orderItem = OrderItem::query()->create([
                    'order_id' => $newOrder->id,
                    'dish_id' => $dish->id,
                    'order_item_status_id' => $pendingItemStatus->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'notes' => $data['notes'],
                ]);

                foreach ($data['complements'] as $compData) {
                    $complement = $compData['complement'];
                    $compQuantity = (int) $compData['quantity'];
                    $compUnitPrice = (float) ($complement->extra_price ?? 0.00);
                    $compSubtotal = round($compUnitPrice * $compQuantity, 2);

                    OrderItemComplement::query()->create([
                        'order_item_id' => $orderItem->id,
                        'complement_id' => $complement->id,
                        'quantity' => $compQuantity,
                        'unit_price' => $compUnitPrice,
                        'subtotal' => $compSubtotal,
                    ]);
                }
            }

            return $newOrder;
        });

        // Si la transacción retornó una respuesta de error JsonResponse
        if ($order instanceof JsonResponse) {
            return $order;
        }

        // Cargar todas las relaciones requeridas para el recurso y WebSocket
        $order->load([
            'restaurantTable.status',
            'waiter.role',
            'type',
            'status',
            'items.dish.category',
            'items.status',
            'items.complements.complement',
        ]);

        // 8. Emitir evento en tiempo real por WebSocket (canal private-cocina) tras confirmar la transacción
        broadcast(new ComandaEnviadaACocina($order));

        return response()->json([
            'message' => 'Comanda registrada y enviada a cocina exitosamente.',
            'order' => new OrderResource($order),
        ], 201);
    }

    #[OA\Get(
        path: '/api/orders/{id}',
        operationId: 'getOrder',
        description: 'Obtiene el detalle completo de una comanda con sus platillos, complementos, cálculos de subtotales y totales.',
        summary: 'Consultar detalle de una comanda',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la comanda a consultar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle de comanda obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'order', ref: '#/components/schemas/OrderResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Comanda no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Comanda no encontrada.')]
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
        $order = Order::query()->with([
            'restaurantTable.status',
            'waiter.role',
            'type',
            'status',
            'items.dish.category',
            'items.status',
            'items.complements.complement',
        ])->find($id);

        if (! $order) {
            return response()->json([
                'message' => 'Comanda no encontrada.',
            ], 404);
        }

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    #[OA\Post(
        path: '/api/orders/{id}/cancel',
        operationId: 'cancelOrder',
        description: 'Anula una comanda antes de que cocina inicie su preparación. Libera inmediatamente el bloqueo de existencias de insumos asociados, marca la mesa como disponible (si el pedido era en mesa) y notifica en tiempo real a la pantalla de cocina mediante el evento ComandaCancelada en el canal private-cocina.',
        summary: 'Anular comanda antes de preparación',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'ID de la comanda a anular',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            description: 'Motivo o justificación de la cancelación',
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/CancelOrderRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comanda cancelada exitosamente y existencias liberadas.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Comanda cancelada exitosamente y existencias liberadas.'),
                        new OA\Property(property: 'order', ref: '#/components/schemas/OrderResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Comanda no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Comanda no encontrada.')]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'La comanda ya está en preparación, entregada o previamente cancelada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'La comanda ya está en preparación en cocina y no puede cancelarse directamente. Remítase a la modificación de pedidos en curso.')]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'No autorizado. Se requiere rol de Mesero/Cajero o Administrador.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para cancelar comandas. Se requiere rol de Mesero/Cajero o Administrador.')]
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
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reasonText = $request->getResolvedReason();

        $result = DB::transaction(function () use ($id, $user, $reasonText) {
            $order = Order::query()
                ->with(['status', 'restaurantTable', 'items'])
                ->lockForUpdate()
                ->find($id);

            if (! $order) {
                return response()->json([
                    'message' => 'Comanda no encontrada.',
                ], 404);
            }

            $currentStatus = $order->status?->name;

            if ($currentStatus === OrderStatus::CANCELADA) {
                return response()->json([
                    'message' => 'La comanda ya ha sido cancelada previamente.',
                ], 422);
            }

            if ($currentStatus === OrderStatus::EN_PREPARACION || $order->preparation_start_time !== null) {
                return response()->json([
                    'message' => 'La comanda ya está en preparación en cocina y no puede cancelarse directamente. Remítase a la modificación de pedidos en curso.',
                ], 422);
            }

            if ($currentStatus === OrderStatus::LISTA || $currentStatus === OrderStatus::ENTREGADA) {
                return response()->json([
                    'message' => "La comanda se encuentra en estado '{$currentStatus}' y no puede cancelarse.",
                ], 422);
            }

            if ($currentStatus !== OrderStatus::PENDIENTE) {
                return response()->json([
                    'message' => 'Solo se pueden cancelar comandas en estado pendiente antes de iniciar preparación.',
                ], 422);
            }

            // Actualizar estado de la comanda a cancelada y registrar motivo con responsable
            $cancelledStatus = OrderStatus::query()->where('name', OrderStatus::CANCELADA)->firstOrFail();
            $cancelledItemStatus = OrderItemStatus::query()->where('name', OrderItemStatus::CANCELADO)->firstOrFail();

            $order->order_status_id = $cancelledStatus->id;
            $order->cancellation_reason = "{$reasonText} (Cancelado por {$user->name})";
            $order->save();

            // Marcar todos los ítems de la comanda como cancelados
            $order->items()->update([
                'order_item_status_id' => $cancelledItemStatus->id,
            ]);

            // Liberar la mesa si la comanda era de tipo en mesa
            if ($order->restaurant_table_id) {
                $table = RestaurantTable::query()->lockForUpdate()->find($order->restaurant_table_id);
                if ($table) {
                    $availableStatus = TableStatus::query()->where('name', TableStatus::DISPONIBLE)->firstOrFail();
                    $table->table_status_id = $availableStatus->id;
                    $table->save();
                }
            }

            return $order;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        // Cargar relaciones para el recurso y WebSocket
        $result->load([
            'restaurantTable.status',
            'waiter.role',
            'type',
            'status',
            'items.dish.category',
            'items.status',
            'items.complements.complement',
        ]);

        // Emitir evento en tiempo real por WebSocket hacia la pantalla de cocina
        broadcast(new ComandaCancelada($result));

        return response()->json([
            'message' => 'Comanda cancelada exitosamente y existencias liberadas.',
            'order' => new OrderResource($result),
        ], 200);
    }

    #[OA\Get(
        path: '/api/order-statuses',
        operationId: 'listOrderStatuses',
        description: 'Obtiene el catálogo de estados de comandas.',
        summary: 'Listar estados de comanda',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de estados de comanda obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderStatusResource')),
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
    public function statuses(): JsonResponse
    {
        $statuses = OrderStatus::query()->orderBy('id')->get();

        return response()->json([
            'data' => OrderStatusResource::collection($statuses),
        ]);
    }

    #[OA\Get(
        path: '/api/order-types',
        operationId: 'listOrderTypes',
        description: 'Obtiene el catálogo de tipos de pedido (en_mesa o para_llevar).',
        summary: 'Listar tipos de comanda',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de tipos de pedido obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderTypeResource')),
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
    public function types(): JsonResponse
    {
        $types = OrderType::query()->orderBy('id')->get();

        return response()->json([
            'data' => OrderTypeResource::collection($types),
        ]);
    }

    #[OA\Get(
        path: '/api/restaurant-tables',
        operationId: 'listRestaurantTables',
        description: 'Obtiene el listado de mesas del restaurante con su estado actual y disponibilidad.',
        summary: 'Listar mesas del restaurante',
        security: [['bearerAuth' => []]],
        tags: ['Comandas y Pedidos'],
        parameters: [
            new OA\Parameter(name: 'available_only', in: 'query', description: 'Filtrar solo mesas disponibles', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de mesas obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/RestaurantTableResource')),
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
    public function tables(Request $request): JsonResponse
    {
        $query = RestaurantTable::query()->with('status');

        if ($request->has('available_only') && filter_var($request->query('available_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('status', function ($q) {
                $q->where('name', TableStatus::DISPONIBLE);
            });
        }

        $tables = $query->orderBy('number')->get();

        return response()->json([
            'data' => RestaurantTableResource::collection($tables),
        ]);
    }
}
