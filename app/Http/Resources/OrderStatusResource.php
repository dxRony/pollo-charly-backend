<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderStatusResource',
    title: 'Order Status Resource',
    description: 'Estado del ciclo de vida de la comanda',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del estado', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre clave del estado (pendiente, en_preparacion, lista, entregada, cancelada)', type: 'string', example: 'pendiente'),
    ]
)]
class OrderStatusResource extends JsonResource
{
    /**
     * Transforma el estado de la comanda a un arreglo estructurado para la respuesta JSON.
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
