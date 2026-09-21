<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderResource',
    title: 'Order Resource',
    description: 'Información completa de una comanda con sus platillos, complementos, mesa y totales calculados',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único de la comanda', type: 'integer', example: 1),
        new OA\Property(property: 'code', description: 'Código único de seguimiento de la comanda', type: 'string', example: 'COM-0001'),
        new OA\Property(property: 'restaurant_table_id', description: 'ID de la mesa asignada (null si es para llevar)', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'table', ref: '#/components/schemas/RestaurantTableResource', nullable: true),
        new OA\Property(property: 'waiter_user_id', description: 'ID del mesero/cajero que tomó la comanda', type: 'integer', example: 2),
        new OA\Property(property: 'waiter', description: 'Información del mesero/cajero responsable', type: 'object', nullable: true),
        new OA\Property(property: 'order_type_id', description: 'ID del tipo de comanda', type: 'integer', example: 1),
        new OA\Property(property: 'order_type', description: 'Tipo de pedido (en_mesa o para_llevar)', type: 'string', example: 'en_mesa'),
        new OA\Property(property: 'order_status_id', description: 'ID del estado actual de la comanda', type: 'integer', example: 1),
        new OA\Property(property: 'order_status', description: 'Estado de la comanda (pendiente, en_preparacion, lista, entregada, cancelada)', type: 'string', example: 'pendiente'),
        new OA\Property(property: 'cancellation_reason', description: 'Motivo de anulación (si fue cancelada)', type: 'string', nullable: true),
        new OA\Property(property: 'notes', description: 'Observaciones generales del pedido', type: 'string', nullable: true, example: 'Comensales con prisa'),
        new OA\Property(property: 'preparation_start_time', description: 'Fecha y hora en que cocina inició la preparación', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItemResource')),
        new OA\Property(property: 'items_count', description: 'Cantidad total de líneas de platillos solicitados', type: 'integer', example: 2),
        new OA\Property(property: 'active_items_count', description: 'Cantidad de líneas activas vigentes a facturar', type: 'integer', example: 2),
        new OA\Property(property: 'subtotal', description: 'Subtotal acumulado de platillos y complementos activos', type: 'number', format: 'float', example: 95.00),
        new OA\Property(property: 'total', description: 'Total a pagar por la comanda', type: 'number', format: 'float', example: 95.00),
        new OA\Property(property: 'is_modification_restricted', description: 'Indica si la comanda tiene bloqueada la eliminación de platillos (solo adición)', type: 'boolean', example: false),
        new OA\Property(property: 'can_remove_items', description: 'Indica si se pueden eliminar o modificar libremente los platillos de la comanda', type: 'boolean', example: true),
        new OA\Property(property: 'modification_restriction_reason', description: 'Motivo de restricción a solo adición si aplica', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de registro de la comanda', type: 'string', format: 'date-time', example: '2026-09-20T12:00:00.000000Z'),
        new OA\Property(property: 'updated_at', description: 'Fecha y hora de última modificación', type: 'string', format: 'date-time', example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class OrderResource extends JsonResource
{
    /**
     * Transforma la comanda a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalCalculado = 0.00;
        $activeItemsCount = 0;

        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $statusName = $item->relationLoaded('status') && $item->status
                    ? $item->status->name
                    : null;

                // Los platillos eliminados o cancelados no se computan en los totales a facturar
                if ($statusName === \App\Models\OrderItemStatus::ELIMINADO || $statusName === \App\Models\OrderItemStatus::CANCELADO) {
                    continue;
                }

                $activeItemsCount++;
                $itemSubtotal = (float) $item->subtotal;
                $complementsSubtotal = $item->relationLoaded('complements')
                    ? (float) $item->complements->sum('subtotal')
                    : 0.00;

                $totalCalculado += ($itemSubtotal + $complementsSubtotal);
            }
        }

        $totalCalculado = round($totalCalculado, 2);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'restaurant_table_id' => $this->restaurant_table_id,
            'table' => $this->relationLoaded('restaurantTable') && $this->restaurantTable
                ? new RestaurantTableResource($this->restaurantTable)
                : null,
            'waiter_user_id' => $this->waiter_user_id,
            'waiter' => $this->relationLoaded('waiter') && $this->waiter
                ? [
                    'id' => $this->waiter->id,
                    'name' => $this->waiter->name,
                    'email' => $this->waiter->email,
                ]
                : null,
            'order_type_id' => $this->order_type_id,
            'order_type' => $this->relationLoaded('type') && $this->type
                ? $this->type->name
                : null,
            'order_status_id' => $this->order_status_id,
            'order_status' => $this->relationLoaded('status') && $this->status
                ? $this->status->name
                : null,
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'preparation_start_time' => $this->preparation_start_time?->toISOString(),
            'items' => $this->relationLoaded('items')
                ? OrderItemResource::collection($this->items)
                : [],
            'items_count' => $this->relationLoaded('items')
                ? $this->items->count()
                : 0,
            'active_items_count' => $activeItemsCount,
            'subtotal' => $totalCalculado,
            'total' => $totalCalculado,
            'is_modification_restricted' => $this->resource->isModificationRestricted(),
            'can_remove_items' => ! $this->resource->isModificationRestricted(),
            'modification_restriction_reason' => $this->resource->getModificationRestrictionReason(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
