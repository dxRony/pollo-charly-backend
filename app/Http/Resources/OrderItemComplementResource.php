<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderItemComplementResource',
    title: 'Order Item Complement Resource',
    description: 'Complemento o guarnición adicional asociado a un ítem de la comanda',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del registro de complemento en la comanda', type: 'integer', example: 1),
        new OA\Property(property: 'complement_id', description: 'ID del complemento en el catálogo', type: 'integer', example: 3),
        new OA\Property(property: 'complement', ref: '#/components/schemas/ComplementResource', nullable: true),
        new OA\Property(property: 'quantity', description: 'Cantidad solicitada del complemento', type: 'integer', example: 1),
        new OA\Property(property: 'unit_price', description: 'Precio unitario del complemento', type: 'number', format: 'float', example: 5.00),
        new OA\Property(property: 'subtotal', description: 'Subtotal calculado (cantidad × precio unitario)', type: 'number', format: 'float', example: 5.00),
    ]
)]
class OrderItemComplementResource extends JsonResource
{
    /**
     * Transforma el complemento del ítem de la comanda a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'complement_id' => $this->complement_id,
            'complement' => $this->relationLoaded('complement') && $this->complement
                ? new ComplementResource($this->complement)
                : null,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
