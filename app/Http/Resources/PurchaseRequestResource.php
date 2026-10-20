<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin PurchaseRequest
 */
#[OA\Schema(
    schema: 'PurchaseRequestResource',
    title: 'Purchase Request Resource',
    description: 'Solicitud de compra generada a partir de alertas de reposición o creada manualmente',
    properties: [
        new OA\Property(property: 'id', description: 'ID de la solicitud', type: 'integer', example: 1),
        new OA\Property(property: 'requester_user_id', description: 'ID del usuario solicitante', type: 'integer', example: 1),
        new OA\Property(property: 'requester_user_name', description: 'Nombre del usuario solicitante', type: 'string', example: 'Administradora'),
        new OA\Property(property: 'purchase_request_status_id', description: 'ID del estado', type: 'integer', example: 1),
        new OA\Property(property: 'status', description: 'Nombre clave del estado (pendiente, aprobada, rechazada_sin_comprar, procesada)', type: 'string', example: 'pendiente'),
        new OA\Property(property: 'reason', description: 'Motivo u observaciones de la solicitud', type: 'string', nullable: true, example: 'Reposición por alerta de stock mínimo'),
        new OA\Property(property: 'items', description: 'Lista de insumos solicitados con cantidades sugeridas y aprobadas', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseRequestItemResource')),
        new OA\Property(property: 'supply_alert_ids', description: 'IDs de alertas de reposición vinculadas', type: 'array', items: new OA\Items(type: 'integer'), example: [3]),
        new OA\Property(property: 'purchase_orders', description: 'Órdenes de compra generadas a partir de esta solicitud', type: 'array', items: new OA\Items(type: 'object'), example: [['id' => 1, 'code' => 'OC-2026-0001', 'status' => 'solicitada', 'total' => 1250.0]]),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de creación', type: 'string', format: 'date-time', example: '2026-10-07T12:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Fecha y hora de última actualización', type: 'string', format: 'date-time', example: '2026-10-07T12:00:00Z'),
    ]
)]
class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requester_user_id' => $this->requester_user_id,
            'requester_user_name' => $this->requesterUser?->name,
            'purchase_request_status_id' => $this->purchase_request_status_id,
            'status' => $this->status?->name,
            'reason' => $this->reason,
            'items' => PurchaseRequestItemResource::collection($this->whenLoaded('items')),
            'supply_alert_ids' => $this->relationLoaded('supplyAlerts')
                ? $this->supplyAlerts->pluck('id')->values()
                : [],
            'purchase_orders' => $this->relationLoaded('purchaseOrders')
                ? $this->purchaseOrders->map(fn ($order) => [
                    'id' => $order->id,
                    'code' => $order->code,
                    'status' => $order->status?->name,
                    'total' => (float) $order->total,
                ])->values()
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
