<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'InventoryMovementTypeResource',
    title: 'Inventory Movement Type Resource',
    description: 'Tipo de movimiento de inventario',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del tipo de movimiento', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre o clave del tipo de movimiento', type: 'string', example: 'compra_entrada'),
    ]
)]
class InventoryMovementTypeResource extends JsonResource
{
    /**
     * Transforma el tipo de movimiento a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
