<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin PurchaseOrder
 */
#[OA\Schema(
    schema: 'PurchaseOrderResource',
    title: 'Purchase Order Resource',
    description: 'Orden de compra o entrega de proveedor',
    properties: [
        new OA\Property(property: 'id', description: 'ID de la orden', type: 'integer', example: 1),
        new OA\Property(property: 'code', description: 'Código único de orden', type: 'string', example: 'OC-2026-0001'),
        new OA\Property(property: 'supplier_id', description: 'ID del proveedor', type: 'integer', example: 2),
        new OA\Property(property: 'supplier_name', description: 'Nombre del proveedor', type: 'string', example: 'Distribuidora Avícola'),
        new OA\Property(property: 'admin_user_id', description: 'ID del administrador que generó la orden', type: 'integer', example: 1),
        new OA\Property(property: 'purchase_order_status_id', description: 'ID del estado', type: 'integer', example: 2),
        new OA\Property(property: 'status', description: 'Estado (solicitada, recibida_completa, recibida_con_incidencia, cancelada)', type: 'string', example: 'recibida_completa'),
        new OA\Property(property: 'total', description: 'Total de la orden', type: 'number', example: 1500.00),
        new OA\Property(property: 'expected_date', description: 'Fecha prevista de entrega', type: 'string', format: 'date', nullable: true, example: '2026-10-08'),
        new OA\Property(property: 'received_date', description: 'Fecha efectiva de recepción', type: 'string', format: 'date', nullable: true, example: '2026-10-08'),
        new OA\Property(property: 'items', description: 'Ítems de la orden', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseOrderItemResource')),
        new OA\Property(property: 'delivery_incidents', description: 'Incidencias asociadas a esta entrega', type: 'array', items: new OA\Items(ref: '#/components/schemas/DeliveryIncidentResource')),
        new OA\Property(property: 'created_at', description: 'Fecha de creación', type: 'string', format: 'date-time', example: '2026-10-07T12:00:00Z'),
    ]
)]
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->company_name,
            'admin_user_id' => $this->admin_user_id,
            'purchase_order_status_id' => $this->purchase_order_status_id,
            'status' => $this->status?->name,
            'total' => (float) $this->total,
            'expected_date' => $this->expected_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'delivery_incidents' => DeliveryIncidentResource::collection($this->whenLoaded('deliveryIncidents')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
