<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderTypeResource',
    title: 'Order Type Resource',
    description: 'Tipo de comanda o pedido (en_mesa o para_llevar)',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del tipo de comanda', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre del tipo de comanda (en_mesa, para_llevar)', type: 'string', example: 'en_mesa'),
    ]
)]
class OrderTypeResource extends JsonResource
{
    /**
     * Transforma el tipo de comanda a un arreglo estructurado para la respuesta JSON.
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
