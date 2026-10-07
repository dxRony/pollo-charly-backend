<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeliveryIncidentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin DeliveryIncidentStatus
 */
#[OA\Schema(
    schema: 'DeliveryIncidentStatusResource',
    title: 'Delivery Incident Status Resource',
    description: 'Estado de una incidencia de entrega',
    properties: [
        new OA\Property(property: 'id', description: 'ID único del estado', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre del estado (reportada, en_correccion, resuelta)', type: 'string', example: 'reportada'),
    ]
)]
class DeliveryIncidentStatusResource extends JsonResource
{
    /**
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
