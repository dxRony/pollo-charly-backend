<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin PurchaseOrderItem
 */
#[OA\Schema(
    schema: 'PurchaseOrderItemResource',
    title: 'Purchase Order Item Resource',
    description: 'Ítem de una orden de compra a proveedor',
    properties: [
        new OA\Property(property: 'id', description: 'ID del ítem', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', description: 'ID del insumo', type: 'integer', example: 2),
        new OA\Property(property: 'supply_name', description: 'Nombre del insumo', type: 'string', example: 'Pechuga de Pollo'),
        new OA\Property(property: 'measurement_unit', description: 'Unidad de medida', type: 'string', example: 'Kilogramos'),
        new OA\Property(property: 'ordered_quantity', description: 'Cantidad pedida', type: 'number', example: 20.00),
        new OA\Property(property: 'received_quantity', description: 'Cantidad recibida', type: 'number', nullable: true, example: 20.00),
        new OA\Property(property: 'unit_price', description: 'Precio unitario pactado', type: 'number', example: 35.00),
        new OA\Property(property: 'subtotal', description: 'Subtotal del ítem', type: 'number', example: 700.00),
    ]
)]
class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_id' => $this->supply_id,
            'supply_name' => $this->supply?->name,
            'measurement_unit' => $this->supply?->measurementUnit?->name,
            'ordered_quantity' => (float) $this->ordered_quantity,
            'received_quantity' => $this->received_quantity !== null ? (float) $this->received_quantity : null,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
