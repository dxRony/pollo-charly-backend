<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SupplyAlert\AttendSupplyAlertRequest;
use App\Http\Requests\SupplyAlert\CreateSupplyAlertRequest;
use App\Http\Resources\AlertOriginResource;
use App\Http\Resources\AlertStatusResource;
use App\Http\Resources\SupplyAlertResource;
use App\Models\AlertOrigin;
use App\Models\AlertStatus;
use App\Models\Role;
use App\Models\Supply;
use App\Models\SupplyAlert;
use App\Models\User;
use App\Notifications\SupplyAlertNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SupplyAlertController extends Controller
{
    #[OA\Get(
        path: '/api/supply-alerts',
        operationId: 'listSupplyAlerts',
        description: 'Obtiene el listado paginado de alertas de reposición de insumos registradas, permitiendo filtrar por estado (pendientes o atendidas), origen (manual o automático), insumo y rango de fechas.',
        summary: 'Listar alertas de reposición',
        security: [['bearerAuth' => []]],
        tags: ['Alertas de Insumos'],
        parameters: [
            new OA\Parameter(name: 'pending', in: 'query', description: 'Filtrar únicamente alertas pendientes sin atender', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por nombre clave del estado (pending o attended)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'alert_status_id', in: 'query', description: 'Filtrar por ID del estado de alerta', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'origin', in: 'query', description: 'Filtrar por nombre clave del origen (manual o automatic)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'alert_origin_id', in: 'query', description: 'Filtrar por ID del origen de alerta', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'supply_id', in: 'query', description: 'Filtrar por ID del producto o insumo', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Fecha inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Fecha final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Buscar por observaciones o nombre/código del insumo', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página (por defecto 15)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de alertas obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SupplyAlertResource')),
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
        $query = SupplyAlert::query()->with([
            'supply.measurementUnit',
            'origin',
            'status',
            'user.role',
        ]);

        // Filtro rápido por alertas pendientes
        if ($request->has('pending') && filter_var($request->query('pending'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('status', function ($q) {
                $q->where('name', AlertStatus::PENDING);
            });
        } elseif ($request->filled('alert_status_id')) {
            $query->where('alert_status_id', (int) $request->query('alert_status_id'));
        } elseif ($request->filled('status')) {
            $status = (string) $request->query('status');
            $query->whereHas('status', function ($q) use ($status) {
                $q->where('name', $status);
            });
        }

        // Filtro por origen
        if ($request->filled('alert_origin_id')) {
            $query->where('alert_origin_id', (int) $request->query('alert_origin_id'));
        } elseif ($request->filled('origin')) {
            $origin = (string) $request->query('origin');
            $query->whereHas('origin', function ($q) use ($origin) {
                $q->where('name', $origin);
            });
        }

        // Filtro por insumo
        if ($request->filled('supply_id')) {
            $query->where('supply_id', (int) $request->query('supply_id'));
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('date_to'));
        }

        // Búsqueda por observaciones o datos del insumo
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('supply', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $alerts = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => SupplyAlertResource::collection($alerts->items()),
            'current_page' => $alerts->currentPage(),
            'per_page' => $alerts->perPage(),
            'total' => $alerts->total(),
            'last_page' => $alerts->lastPage(),
        ]);
    }

    #[OA\Post(
        path: '/api/supply-alerts',
        operationId: 'createSupplyAlert',
        description: 'Registra manualmente una alerta de reposición para un producto o insumo y notifica por correo electrónico a la administradora.',
        summary: 'Generar alerta manual de reposición',
        security: [['bearerAuth' => []]],
        tags: ['Alertas de Insumos'],
        requestBody: new OA\RequestBody(
            description: 'Datos de la alerta manual',
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateSupplyAlertRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Alerta de reposición generada exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Alerta de reposición generada exitosamente.'),
                        new OA\Property(property: 'alert', ref: '#/components/schemas/SupplyAlertResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación (insumo inexistente o inactivo).',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'El producto o insumo seleccionado no existe en el catálogo.'),
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
    public function store(CreateSupplyAlertRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $manualOrigin = AlertOrigin::query()->firstOrCreate(
            ['name' => AlertOrigin::MANUAL],
            ['name' => AlertOrigin::MANUAL]
        );

        $pendingStatus = AlertStatus::query()->firstOrCreate(
            ['name' => AlertStatus::PENDING],
            ['name' => AlertStatus::PENDING]
        );

        $alert = DB::transaction(function () use ($validated, $user, $manualOrigin, $pendingStatus) {
            $supply = Supply::query()->where('id', $validated['supply_id'])->lockForUpdate()->firstOrFail();

            $newAlert = SupplyAlert::query()->create([
                'supply_id' => $supply->id,
                'alert_origin_id' => $manualOrigin->id,
                'alert_status_id' => $pendingStatus->id,
                'user_id' => $user->id,
                'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            ]);

            return $newAlert;
        });

        $alert->load([
            'supply.measurementUnit',
            'origin',
            'status',
            'user.role',
        ]);

        // Notificar por correo electrónico a todas las administradoras activas
        $this->notifyAdministrators($alert);

        return response()->json([
            'message' => 'Alerta de reposición generada exitosamente.',
            'alert' => new SupplyAlertResource($alert),
        ], 201);
    }

    #[OA\Get(
        path: '/api/supply-alerts/{id}',
        operationId: 'getSupplyAlert',
        description: 'Obtiene la información detallada de una alerta de reposición específica.',
        summary: 'Consultar detalle de una alerta de reposición',
        security: [['bearerAuth' => []]],
        tags: ['Alertas de Insumos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la alerta a consultar', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Alerta obtenida exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'alert', ref: '#/components/schemas/SupplyAlertResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Alerta no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Alerta de reposición no encontrada.')]
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
        $alert = SupplyAlert::query()->with([
            'supply.measurementUnit',
            'origin',
            'status',
            'user.role',
        ])->find($id);

        if (! $alert) {
            return response()->json([
                'message' => 'Alerta de reposición no encontrada.',
            ], 404);
        }

        return response()->json([
            'alert' => new SupplyAlertResource($alert),
        ]);
    }

    #[OA\Post(
        path: '/api/supply-alerts/{id}/attend',
        operationId: 'attendSupplyAlert',
        description: 'Marca como atendida una alerta de reposición pendiente y opcionalmente la vincula con una solicitud de compra. Si la alerta ya fue atendida previamente, rechaza la operación.',
        summary: 'Atender alerta de reposición',
        security: [['bearerAuth' => []]],
        tags: ['Alertas de Insumos'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la alerta a marcar como atendida', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Datos opcionales para atender la alerta',
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/AttendSupplyAlertRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Alerta marcada como atendida exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Alerta de reposición marcada como atendida exitosamente.'),
                        new OA\Property(property: 'alert', ref: '#/components/schemas/SupplyAlertResource'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'La alerta ya fue atendida previamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'La alerta de reposición ya ha sido atendida previamente y no puede marcarse nuevamente.'),
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
                description: 'Alerta no encontrada.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Alerta de reposición no encontrada.')]
                )
            ),
        ]
    )]
    public function attend(AttendSupplyAlertRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $alert = SupplyAlert::query()
                ->with(['status', 'supply.measurementUnit', 'origin', 'user.role'])
                ->lockForUpdate()
                ->find($id);

            if (! $alert) {
                return response()->json([
                    'message' => 'Alerta de reposición no encontrada.',
                ], 404);
            }

            if ($alert->status?->name === AlertStatus::ATTENDED) {
                return response()->json([
                    'message' => 'La alerta de reposición ya ha sido atendida previamente y no puede marcarse nuevamente.',
                ], 422);
            }

            $attendedStatus = AlertStatus::query()
                ->where('name', AlertStatus::ATTENDED)
                ->firstOrFail();

            $alert->alert_status_id = $attendedStatus->id;

            if ($request->filled('purchase_request_id')) {
                $alert->purchase_request_id = (int) $request->input('purchase_request_id');
            }

            if ($request->filled('notes')) {
                $notes = trim((string) $request->input('notes'));
                $alert->notes = ($alert->notes ? $alert->notes . ' | Atención: ' : 'Atención: ') . $notes;
            }

            $alert->save();

            $alert->load([
                'supply.measurementUnit',
                'origin',
                'status',
                'user.role',
            ]);

            return response()->json([
                'message' => 'Alerta de reposición marcada como atendida exitosamente.',
                'alert' => new SupplyAlertResource($alert),
            ]);
        });
    }

    #[OA\Get(
        path: '/api/alert-statuses',
        operationId: 'listAlertStatuses',
        description: 'Obtiene el catálogo de estados disponibles para las alertas de reposición (pending y attended).',
        summary: 'Listar estados de alertas',
        security: [['bearerAuth' => []]],
        tags: ['Alertas de Insumos'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de estados obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertStatusResource')),
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
        $statuses = AlertStatus::query()->orderBy('id')->get();

        return response()->json([
            'data' => AlertStatusResource::collection($statuses),
        ]);
    }

    #[OA\Get(
        path: '/api/alert-origins',
        operationId: 'listAlertOrigins',
        description: 'Obtiene el catálogo de orígenes disponibles para las alertas de reposición (manual y automatic).',
        summary: 'Listar orígenes de alertas',
        security: [['bearerAuth' => []]],
        tags: ['Alertas de Insumos'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Catálogo de orígenes obtenido exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlertOriginResource')),
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
    public function origins(): JsonResponse
    {
        $origins = AlertOrigin::query()->orderBy('id')->get();

        return response()->json([
            'data' => AlertOriginResource::collection($origins),
        ]);
    }

    /**
     * Envía una notificación por correo electrónico a todas las administradoras activas del sistema.
     */
    public static function notifyAdministrators(SupplyAlert $alert): void
    {
        $alert->loadMissing(['supply.measurementUnit', 'origin', 'status', 'user']);

        $admins = User::query()
            ->whereRelation('role', 'name', Role::ADMINISTRADOR)
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new SupplyAlertNotification($alert));
        }
    }
}
