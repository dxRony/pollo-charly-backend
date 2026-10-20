<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeliveryIncidentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin DeliveryIncidentType
 */
#[OA\Schema(
    schema: 'DeliveryIncidentTypeResource',
    title: 'Delivery Incident Type Resource',
    description: 'Tipo de incidencia en recepción de entregas',
    properties: [
        new OA\Property(property: 'id', description: 'ID único del tipo de incidencia', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre identificador del tipo de incidencia', type: 'string', example: 'peso_incompleto'),
    ]
)]
class DeliveryIncidentTypeResource extends JsonResource
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
