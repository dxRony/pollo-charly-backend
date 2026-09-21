<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AlertStatusResource',
    title: 'Alert Status Resource',
    description: 'Estado de la alerta de reposición (pending o attended)',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del estado', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre o clave del estado de la alerta', type: 'string', example: 'pending'),
    ]
)]
class AlertStatusResource extends JsonResource
{
    /**
     * Transforma el estado de la alerta a un arreglo estructurado para la respuesta JSON.
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
