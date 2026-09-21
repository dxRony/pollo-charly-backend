<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AlertOriginResource',
    title: 'Alert Origin Resource',
    description: 'Origen de la alerta de reposición (manual o automática)',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del origen', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre o clave del origen de alerta', type: 'string', example: 'manual'),
    ]
)]
class AlertOriginResource extends JsonResource
{
    /**
     * Transforma el origen de la alerta a un arreglo estructurado para la respuesta JSON.
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
