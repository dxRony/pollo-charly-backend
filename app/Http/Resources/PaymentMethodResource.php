<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaymentMethodResource',
    title: 'Payment Method Resource',
    description: 'Método de pago admitido en el restaurante (efectivo, tarjeta, transferencia)',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del método de pago', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre clave del método de pago', type: 'string', example: 'efectivo'),
    ]
)]
class PaymentMethodResource extends JsonResource
{
    /**
     * Transforma el método de pago a un arreglo estructurado para la respuesta JSON.
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
