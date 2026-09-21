<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'InventoryMovementResource',
    title: 'Inventory Movement Resource',
    description: 'Información completa del movimiento de inventario',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del movimiento', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', description: 'ID del producto o insumo', type: 'integer', example: 1),
        new OA\Property(property: 'supply', ref: '#/components/schemas/SupplyResource', nullable: true),
        new OA\Property(property: 'inventory_movement_type_id', description: 'ID del tipo de movimiento', type: 'integer', example: 1),
        new OA\Property(property: 'movement_type', ref: '#/components/schemas/InventoryMovementTypeResource', nullable: true),
        new OA\Property(property: 'type', description: 'Nombre clave del tipo de movimiento', type: 'string', example: 'compra_entrada'),
        new OA\Property(property: 'user_id', description: 'ID del usuario que registró el movimiento', type: 'integer', example: 1),
        new OA\Property(property: 'user', description: 'Información básica del usuario que registró el movimiento', type: 'object', nullable: true),
        new OA\Property(property: 'quantity', description: 'Cantidad afectada en el movimiento', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'previous_stock', description: 'Existencia previa al movimiento', type: 'number', format: 'float', example: 20.00),
        new OA\Property(property: 'new_stock', description: 'Existencia resultante o proyectada tras el movimiento', type: 'number', format: 'float', example: 30.00),
        new OA\Property(property: 'reason', description: 'Motivo u observación del movimiento', type: 'string', nullable: true, example: 'Merma por producto vencido'),
        new OA\Property(property: 'order_id', description: 'ID de comanda asociada (si aplica)', type: 'integer', nullable: true),
        new OA\Property(property: 'order_item_id', description: 'ID del ítem de comanda asociado (si aplica)', type: 'integer', nullable: true),
        new OA\Property(property: 'purchase_order_id', description: 'ID de la orden de compra asociada (si aplica)', type: 'integer', nullable: true),
        new OA\Property(property: 'adjustment_status_type_id', description: 'ID del estado de ajuste (si aplica)', type: 'integer', nullable: true),
        new OA\Property(property: 'adjustment_status', ref: '#/components/schemas/AdjustmentStatusTypeResource', nullable: true),
        new OA\Property(property: 'approver_user_id', description: 'ID de la administradora que aprobó o rechazó el ajuste', type: 'integer', nullable: true),
        new OA\Property(property: 'approver_user', description: 'Información básica del usuario que aprobó o rechazó', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de registro del movimiento', type: 'string', format: 'date-time', example: '2026-09-20T12:00:00.000000Z'),
        new OA\Property(property: 'updated_at', description: 'Fecha y hora de última actualización', type: 'string', format: 'date-time', example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class InventoryMovementResource extends JsonResource
{
    /**
     * Transforma el movimiento de inventario a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_id' => $this->supply_id,
            'supply' => $this->relationLoaded('supply') && $this->supply
                ? new SupplyResource($this->supply)
                : null,
            'inventory_movement_type_id' => $this->inventory_movement_type_id,
            'movement_type' => $this->relationLoaded('movementType') && $this->movementType
                ? new InventoryMovementTypeResource($this->movementType)
                : null,
            'type' => $this->relationLoaded('movementType') && $this->movementType
                ? $this->movementType->name
                : null,
            'user_id' => $this->user_id,
            'user' => $this->relationLoaded('user') && $this->user
                ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->relationLoaded('role') && $this->user->role
                        ? $this->user->role->name
                        : null,
                ]
                : null,
            'quantity' => (float) $this->quantity,
            'previous_stock' => (float) $this->previous_stock,
            'new_stock' => (float) $this->new_stock,
            'reason' => $this->reason,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'purchase_order_id' => $this->purchase_order_id,
            'adjustment_status_type_id' => $this->adjustment_status_type_id,
            'adjustment_status' => $this->relationLoaded('adjustmentStatus') && $this->adjustmentStatus
                ? new AdjustmentStatusTypeResource($this->adjustmentStatus)
                : null,
            'approver_user_id' => $this->approver_user_id,
            'approver_user' => $this->relationLoaded('approverUser') && $this->approverUser
                ? [
                    'id' => $this->approverUser->id,
                    'name' => $this->approverUser->name,
                    'email' => $this->approverUser->email,
                ]
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
