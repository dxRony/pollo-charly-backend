<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AdjustmentStatusTypeResource',
    title: 'Adjustment Status Type Resource',
    description: 'Estado de una solicitud de ajuste de inventario',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del estado de ajuste', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre o clave del estado de ajuste', type: 'string', example: 'pendiente_aprobacion'),
    ]
)]
class AdjustmentStatusTypeResource extends JsonResource
{
    /**
     * Transforma el estado de ajuste a un arreglo estructurado para la respuesta JSON.
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
