<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\CreateInventoryMovementRequest;
use App\Http\Requests\Inventory\ReviewAdjustmentRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\InventoryMovementTypeResource;
use App\Models\AdjustmentStatusType;
use App\Models\AlertOrigin;
use App\Models\AlertStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class InventoryMovementController extends Controller
{
    #[OA\Get(
        path: '/api/inventory-movements',
        operationId: 'listInventoryMovements',
        description: 'Obtiene el listado paginado de movimientos de inventario registrados, permitiendo filtrar por insumo, tipo de movimiento, estado de ajuste, rango de fechas y texto de búsqueda.',
        summary: 'Listar movimientos de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Movimientos de Inventario'],
        parameters: [
            new OA\Parameter(name: 'supply_id', in: 'query', description: 'Filtrar por ID del producto o insumo', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', description: 'Filtrar por nombre de tipo de movimiento (compra, salida, merma, ajuste, compra_entrada, etc.)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'inventory_movement_type_id', in: 'query', description: 'Filtrar por ID del tipo de movimiento', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'adjustment_status', in: 'query', description: 'Filtrar por estado del ajuste (pendiente, aprobado, rechazado)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'adjustment_status_type_id', in: 'query', description: 'Filtrar por ID del estado de ajuste', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pending_adjustments', in: 'query', description: 'Filtrar únicamente solicitudes de ajuste pendientes de aprobación', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por motivo o nombre/código de insumo', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de elementos por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de movimientos obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/InventoryMovementResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 50),
                        new OA\Property(property: 'last_page', type: 'integer', example: 4),
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
        $query = InventoryMovement::query()
            ->with([
                'supply.measurementUnit',
                'movementType',
                'user.role',
                'adjustmentStatus',
                'approverUser',
            ]);

        // Filtro por insumo
        if ($request->filled('supply_id')) {
            $query->where('supply_id', (int) $request->query('supply_id'));
        }

        // Filtro por tipo de movimiento (ID o nombre)
        if ($request->filled('inventory_movement_type_id')) {
            $query->where('inventory_movement_type_id', (int) $request->query('inventory_movement_type_id'));
        } elseif ($request->filled('type')) {
            $type = (string) $request->query('type');
            $typeMap = [
                'compra' => InventoryMovementType::COMPRA_ENTRADA,
                'salida' => InventoryMovementType::CONSUMO_VENTA,
                'merma' => InventoryMovementType::MERMA_DANO,
                'ajuste' => InventoryMovementType::AJUSTE_INVENTARIO,
                'cancelacion' => InventoryMovementType::CANCELACION_PEDIDO,
            ];
            $canonicalType = $typeMap[$type] ?? $type;

            $query->whereHas('movementType', function ($q) use ($canonicalType) {
                $q->where('name', $canonicalType);
            });
        }

        // Filtro por estado de ajuste
        if ($request->has('pending_adjustments') && filter_var($request->query('pending_adjustments'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('adjustmentStatus', function ($q) {
                $q->where('name', AdjustmentStatusType::PENDIENTE_APROBACION);
            });
        } elseif ($request->filled('adjustment_status_type_id')) {
            $query->where('adjustment_status_type_id', (int) $request->query('adjustment_status_type_id'));
        } elseif ($request->filled('adjustment_status')) {
            $status = (string) $request->query('adjustment_status');
            $statusMap = [
                'pendiente' => AdjustmentStatusType::PENDIENTE_APROBACION,
                'pendiente_aprobacion' => AdjustmentStatusType::PENDIENTE_APROBACION,
                'aprobado' => AdjustmentStatusType::APROBADO,
                'rechazado' => AdjustmentStatusType::RECHAZADO,
            ];
            $canonicalStatus = $statusMap[$status] ?? $status;

            $query->whereHas('adjustmentStatus', function ($q) use ($canonicalStatus) {
                $q->where('name', $canonicalStatus);
            });
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        // Búsqueda por motivo o datos del insumo
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('supply', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $movements = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => InventoryMovementResource::collection($movements->items()),
            'current_page' => $movements->currentPage(),
            'per_page' => $movements->perPage(),
            'total' => $movements->total(),
            'last_page' => $movements->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/inventory-movements',
        operationId: 'createInventoryMovement',
        description: 'Registra un movimiento de inventario (compra, salida, merma o ajuste) sobre un insumo. Si el movimiento deja la existencia por debajo del mínimo, se genera automáticamente una alerta de reposición. Los ajustes requieren aprobación de administradora.',
        summary: 'Registrar movimiento de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Movimientos de Inventario'],
        requestBody: new OA\RequestBody(
            description: 'Datos del movimiento a registrar',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateInventoryMovementRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Movimiento registrado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Movimiento de compra registrado exitosamente.'),
                        new OA\Property(property: 'movement', ref: '#/components/schemas/InventoryMovementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación o existencia insuficiente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Existencia insuficiente para registrar la salida.'),
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
        ]
    )]
    public function store(CreateInventoryMovementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Determinar el tipo de movimiento
        $canonicalTypeName = $request->getResolvedTypeName();
        $movementType = InventoryMovementType::query()->where('name', $canonicalTypeName)->firstOrFail();

        return DB::transaction(function () use ($validated, $user, $canonicalTypeName, $movementType) {
            // Bloqueo pesimista para evitar condiciones de carrera en stock concurrente
            $supply = Supply::query()->where('id', $validated['supply_id'])->lockForUpdate()->firstOrFail();

            if (! $supply->is_active) {
                return response()->json([
                    'message' => 'El producto o insumo seleccionado se encuentra inactivo.',
                ], 422);
            }

            $previousStock = (float) $supply->current_stock;
            $reason = isset($validated['reason']) ? trim((string) $validated['reason']) : null;

            switch ($canonicalTypeName) {
                case InventoryMovementType::COMPRA_ENTRADA:
                    $quantity = (float) $validated['quantity'];
                    $newStock = round($previousStock + $quantity, 2);

                    $supply->current_stock = $newStock;
                    $supply->save();

                    $movement = InventoryMovement::query()->create([
                        'supply_id' => $supply->id,
                        'inventory_movement_type_id' => $movementType->id,
                        'user_id' => $user->id,
                        'quantity' => $quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reason' => $reason,
                        'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                        'adjustment_status_type_id' => null,
                        'approver_user_id' => null,
                    ]);

                    $message = 'Movimiento de compra registrado exitosamente.';
                    break;

                case InventoryMovementType::CONSUMO_VENTA:
                    $quantity = (float) $validated['quantity'];

                    // Validación de existencia suficiente
                    if ($previousStock < $quantity) {
                        return response()->json([
                            'message' => "Existencia insuficiente para realizar la salida. Existencia actual: {$previousStock}, cantidad solicitada: {$quantity}.",
                        ], 422);
                    }

                    $newStock = round($previousStock - $quantity, 2);
                    $supply->current_stock = $newStock;
                    $supply->save();

                    $movement = InventoryMovement::query()->create([
                        'supply_id' => $supply->id,
                        'inventory_movement_type_id' => $movementType->id,
                        'user_id' => $user->id,
                        'quantity' => $quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reason' => $reason,
                        'order_id' => $validated['order_id'] ?? null,
                        'order_item_id' => $validated['order_item_id'] ?? null,
                        'adjustment_status_type_id' => null,
                        'approver_user_id' => null,
                    ]);

                    // Evaluación de alerta por stock mínimo
                    $this->checkAndCreateLowStockAlert($supply, $user);

                    $message = 'Movimiento de salida registrado exitosamente.';
                    break;

                case InventoryMovementType::MERMA_DANO:
                    $quantity = (float) $validated['quantity'];

                    // Validación de existencia suficiente para merma
                    if ($previousStock < $quantity) {
                        return response()->json([
                            'message' => "Existencia insuficiente para registrar la merma. Existencia actual: {$previousStock}, cantidad solicitada: {$quantity}.",
                        ], 422);
                    }

                    $newStock = round($previousStock - $quantity, 2);
                    $supply->current_stock = $newStock;
                    $supply->save();

                    $movement = InventoryMovement::query()->create([
                        'supply_id' => $supply->id,
                        'inventory_movement_type_id' => $movementType->id,
                        'user_id' => $user->id,
                        'quantity' => $quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reason' => $reason,
                        'adjustment_status_type_id' => null,
                        'approver_user_id' => null,
                    ]);

                    // Evaluación de alerta por stock mínimo
                    $this->checkAndCreateLowStockAlert($supply, $user);

                    $message = 'Movimiento de merma registrado exitosamente.';
                    break;

                case InventoryMovementType::AJUSTE_INVENTARIO:
                    // En ajuste, la existencia corregida se toma de new_stock o quantity
                    $targetStock = isset($validated['new_stock'])
                        ? (float) $validated['new_stock']
                        : (float) $validated['quantity'];

                    $quantity = isset($validated['quantity'])
                        ? (float) $validated['quantity']
                        : round(abs($targetStock - $previousStock), 2);

                    $pendingStatus = AdjustmentStatusType::query()
                        ->where('name', AdjustmentStatusType::PENDIENTE_APROBACION)
                        ->firstOrFail();

                    // La existencia física NO se modifica hasta la aprobación de la administradora
                    $movement = InventoryMovement::query()->create([
                        'supply_id' => $supply->id,
                        'inventory_movement_type_id' => $movementType->id,
                        'user_id' => $user->id,
                        'quantity' => $quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $targetStock,
                        'reason' => $reason,
                        'adjustment_status_type_id' => $pendingStatus->id,
                        'approver_user_id' => null,
                    ]);

                    $message = 'Solicitud de ajuste de inventario registrada exitosamente y enviada a revisión.';
                    break;

                default:
                    return response()->json([
                        'message' => 'Tipo de movimiento no soportado para registro manual.',
                    ], 422);
            }

            $movement->load([
                'supply.measurementUnit',
                'movementType',
                'user.role',
                'adjustmentStatus',
                'approverUser',
            ]);

            return response()->json([
                'message' => $message,
                'movement' => new InventoryMovementResource($movement),
            ], 201);
        });
    }

    #[OA\Get(
        path: '/api/inventory-movements/{id}',
        operationId: 'getInventoryMovement',
        description: 'Obtiene el detalle completo de un movimiento de inventario específico.',
        summary: 'Consultar detalle de un movimiento de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Movimientos de Inventario'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del movimiento a consultar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Movimiento obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'movement', ref: '#/components/schemas/InventoryMovementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Movimiento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Movimiento de inventario no encontrado.')]
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
        $movement = InventoryMovement::query()
            ->with([
                'supply.measurementUnit',
                'movementType',
                'user.role',
                'adjustmentStatus',
                'approverUser',
            ])
            ->find($id);

        if (! $movement) {
            return response()->json([
                'message' => 'Movimiento de inventario no encontrado.',
            ], 404);
        }

        return response()->json([
            'movement' => new InventoryMovementResource($movement),
        ]);
    }

    #[OA\Post(
        path: '/api/inventory-movements/{id}/approve',
        operationId: 'approveInventoryAdjustment',
        description: 'Aprueba una solicitud de ajuste de inventario pendiente. Corrige la existencia física registrada en almacén con la cantidad acordada y guarda la trazabilidad del usuario aprobador.',
        summary: 'Aprobar solicitud de ajuste de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Movimientos de Inventario'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del movimiento de ajuste a aprobar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Notas u observaciones opcionales de aprobación',
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ReviewAdjustmentRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ajuste de inventario aprobado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Ajuste de inventario aprobado y existencia actualizada exitosamente.'),
                        new OA\Property(property: 'movement', ref: '#/components/schemas/InventoryMovementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'El movimiento no es un ajuste o no se encuentra en estado pendiente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Solo se pueden aprobar solicitudes de ajuste que se encuentren en estado pendiente de aprobación.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acceso denegado (requiere rol Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Movimiento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Movimiento de inventario no encontrado.')]
                )
            ),
        ]
    )]
    public function approveAdjustment(ReviewAdjustmentRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($request, $id, $user) {
            $movement = InventoryMovement::query()
                ->with(['movementType', 'adjustmentStatus'])
                ->lockForUpdate()
                ->find($id);

            if (! $movement) {
                return response()->json([
                    'message' => 'Movimiento de inventario no encontrado.',
                ], 404);
            }

            if ($movement->movementType?->name !== InventoryMovementType::AJUSTE_INVENTARIO) {
                return response()->json([
                    'message' => 'Solo se pueden aprobar movimientos de tipo ajuste de inventario.',
                ], 422);
            }

            if ($movement->adjustmentStatus?->name !== AdjustmentStatusType::PENDIENTE_APROBACION) {
                return response()->json([
                    'message' => 'Solo se pueden aprobar solicitudes de ajuste que se encuentren en estado pendiente de aprobación.',
                ], 422);
            }

            $supply = Supply::query()->where('id', $movement->supply_id)->lockForUpdate()->firstOrFail();

            // Actualizar stock de almacén con la cantidad corregida
            $previousStock = (float) $supply->current_stock;
            $supply->current_stock = $movement->new_stock;
            $supply->save();

            // Estado aprobado
            $approvedStatus = AdjustmentStatusType::query()
                ->where('name', AdjustmentStatusType::APROBADO)
                ->firstOrFail();

            $movement->previous_stock = $previousStock;
            $movement->adjustment_status_type_id = $approvedStatus->id;
            $movement->approver_user_id = $user->id;

            if ($request->filled('reason')) {
                $additionalNotes = trim((string) $request->input('reason'));
                $movement->reason = ($movement->reason ? $movement->reason . ' | Aprobación: ' : 'Aprobación: ') . $additionalNotes;
            }

            $movement->save();

            // Evaluación de alerta automática si la nueva existencia queda bajo el mínimo
            $this->checkAndCreateLowStockAlert($supply, $user);

            $movement->load([
                'supply.measurementUnit',
                'movementType',
                'user.role',
                'adjustmentStatus',
                'approverUser',
            ]);

            return response()->json([
                'message' => 'Ajuste de inventario aprobado y existencia actualizada exitosamente.',
                'movement' => new InventoryMovementResource($movement),
            ]);
        });
    }

    #[OA\Post(
        path: '/api/inventory-movements/{id}/reject',
        operationId: 'rejectInventoryAdjustment',
        description: 'Rechaza una solicitud de ajuste de inventario pendiente sin modificar la existencia física registrada en almacén.',
        summary: 'Rechazar solicitud de ajuste de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Movimientos de Inventario'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del movimiento de ajuste a rechazar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Motivo u observación del rechazo',
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ReviewAdjustmentRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ajuste de inventario rechazado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Ajuste de inventario rechazado exitosamente.'),
                        new OA\Property(property: 'movement', ref: '#/components/schemas/InventoryMovementResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'El movimiento no es un ajuste o no se encuentra en estado pendiente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Solo se pueden rechazar solicitudes de ajuste que se encuentren en estado pendiente de aprobación.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acceso denegado (requiere rol Administrador).',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'No tienes permisos para acceder a este módulo. Se requiere rol de administradora.')]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Movimiento no encontrado.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Movimiento de inventario no encontrado.')]
                )
            ),
        ]
    )]
    public function rejectAdjustment(ReviewAdjustmentRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($request, $id, $user) {
            $movement = InventoryMovement::query()
                ->with(['movementType', 'adjustmentStatus'])
                ->lockForUpdate()
                ->find($id);

            if (! $movement) {
                return response()->json([
                    'message' => 'Movimiento de inventario no encontrado.',
                ], 404);
            }

            if ($movement->movementType?->name !== InventoryMovementType::AJUSTE_INVENTARIO) {
                return response()->json([
                    'message' => 'Solo se pueden rechazar movimientos de tipo ajuste de inventario.',
                ], 422);
            }

            if ($movement->adjustmentStatus?->name !== AdjustmentStatusType::PENDIENTE_APROBACION) {
                return response()->json([
                    'message' => 'Solo se pueden rechazar solicitudes de ajuste que se encuentren en estado pendiente de aprobación.',
                ], 422);
            }

            // Estado rechazado sin modificar la existencia física del insumo
            $rejectedStatus = AdjustmentStatusType::query()
                ->where('name', AdjustmentStatusType::RECHAZADO)
                ->firstOrFail();

            $movement->adjustment_status_type_id = $rejectedStatus->id;
            $movement->approver_user_id = $user->id;

            if ($request->filled('reason')) {
                $rejectionReason = trim((string) $request->input('reason'));
                $movement->reason = ($movement->reason ? $movement->reason . ' | Motivo de rechazo: ' : 'Rechazado: ') . $rejectionReason;
            }

            $movement->save();

            $movement->load([
                'supply.measurementUnit',
                'movementType',
                'user.role',
                'adjustmentStatus',
                'approverUser',
            ]);

            return response()->json([
                'message' => 'Ajuste de inventario rechazado exitosamente.',
                'movement' => new InventoryMovementResource($movement),
            ]);
        });
    }

    #[OA\Get(
        path: '/api/inventory-movement-types',
        operationId: 'listInventoryMovementTypes',
        description: 'Obtiene el catálogo de tipos de movimiento de inventario disponibles en el sistema.',
        summary: 'Listar tipos de movimiento de inventario',
        security: [['bearerAuth' => []]],
        tags: ['Movimientos de Inventario'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de tipos de movimiento obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/InventoryMovementTypeResource')),
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
        $types = InventoryMovementType::query()->orderBy('id')->get();

        return response()->json([
            'data' => InventoryMovementTypeResource::collection($types),
        ]);
    }

    /**
     * Evalúa si la existencia actual de un insumo quedó por debajo del mínimo de referencia
     * y genera automáticamente una alerta de reposición si aún no existe una alerta pendiente.
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
                $alert = SupplyAlert::query()->create([
                    'supply_id' => $supply->id,
                    'alert_origin_id' => $automaticOrigin->id,
                    'alert_status_id' => $pendingStatus->id,
                    'user_id' => $user->id,
                    'notes' => 'Alerta automática generada al quedar la existencia por debajo del mínimo de referencia (' . $supply->minimum_stock . '). Existencia actual: ' . $supply->current_stock,
                ]);

                // Notificar por correo a las administradoras del sistema
                SupplyAlertController::notifyAdministrators($alert);
            }
        }
    }
}
