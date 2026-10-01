<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MeasurementUnitResource',
    title: 'Measurement Unit Resource',
    description: 'Unidad de medida para insumos y recetas',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único de la unidad de medida', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre completo de la unidad de medida', type: 'string', example: 'Kilogramo'),
        new OA\Property(property: 'abbreviation', description: 'Símbolo o abreviatura de la unidad', type: 'string', example: 'kg'),
    ]
)]
class MeasurementUnitResource extends JsonResource
{
    /**
     * Transforma el recurso a un arreglo para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
        ];
    }
}
