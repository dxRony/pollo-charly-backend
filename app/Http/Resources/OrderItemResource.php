<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderItemResource',
    title: 'Order Item Resource',
    description: 'Ítem o platillo individual incluido dentro de una comanda',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del ítem de la comanda', type: 'integer', example: 1),
        new OA\Property(property: 'dish_id', description: 'ID del platillo solicitado', type: 'integer', example: 2),
        new OA\Property(property: 'dish', ref: '#/components/schemas/DishResource', nullable: true),
        new OA\Property(property: 'order_item_status_id', description: 'ID del estado del ítem', type: 'integer', example: 1),
        new OA\Property(property: 'status_name', description: 'Nombre del estado del ítem (pendiente, en_preparacion, etc.)', type: 'string', example: 'pendiente'),
        new OA\Property(property: 'quantity', description: 'Cantidad solicitada del platillo', type: 'integer', example: 2),
        new OA\Property(property: 'unit_price', description: 'Precio unitario del platillo al momento de comandar', type: 'number', format: 'float', example: 45.00),
        new OA\Property(property: 'subtotal', description: 'Subtotal del platillo (cantidad × precio unitario)', type: 'number', format: 'float', example: 90.00),
        new OA\Property(property: 'notes', description: 'Observaciones de preparación (ej. sin cebolla, bien dorado)', type: 'string', nullable: true, example: 'Pechuga bien cocida'),
        new OA\Property(property: 'complements', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItemComplementResource')),
        new OA\Property(property: 'complements_subtotal', description: 'Suma de subtotales de los complementos asociados', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'total_with_complements', description: 'Total de la línea incluyendo platillo y complementos', type: 'number', format: 'float', example: 100.00),
    ]
)]
class OrderItemResource extends JsonResource
{
    /**
     * Transforma el ítem de la comanda a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $complementsSubtotal = $this->relationLoaded('complements')
            ? (float) $this->complements->sum('subtotal')
            : 0.00;

        $itemSubtotal = (float) $this->subtotal;

        return [
            'id' => $this->id,
            'dish_id' => $this->dish_id,
            'dish' => $this->relationLoaded('dish') && $this->dish
                ? new DishResource($this->dish)
                : null,
            'order_item_status_id' => $this->order_item_status_id,
            'status_name' => $this->relationLoaded('status') && $this->status
                ? $this->status->name
                : null,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => $itemSubtotal,
            'notes' => $this->notes,
            'complements' => $this->relationLoaded('complements')
                ? OrderItemComplementResource::collection($this->complements)
                : [],
            'complements_subtotal' => $complementsSubtotal,
            'total_with_complements' => round($itemSubtotal + $complementsSubtotal, 2),
        ];
    }
}
