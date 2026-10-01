<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReceiptTypeResource',
    title: 'Receipt Type Resource',
    description: 'Tipo de comprobante emitido por la venta (ticket, factura)',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del tipo de comprobante', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre clave del tipo de comprobante', type: 'string', example: 'ticket'),
    ]
)]
class ReceiptTypeResource extends JsonResource
{
    /**
     * Transforma el tipo de comprobante a un arreglo estructurado para la respuesta JSON.
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
