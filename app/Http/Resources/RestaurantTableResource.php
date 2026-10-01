<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TableStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RestaurantTableResource',
    title: 'Restaurant Table Resource',
    description: 'Mesa del restaurante con su capacidad y estado de ocupación actual',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único de la mesa', type: 'integer', example: 1),
        new OA\Property(property: 'number', description: 'Número identificador de la mesa', type: 'integer', example: 4),
        new OA\Property(property: 'capacity', description: 'Capacidad de comensales', type: 'integer', example: 4),
        new OA\Property(property: 'table_status_id', description: 'ID del estado de la mesa', type: 'integer', example: 1),
        new OA\Property(property: 'status_name', description: 'Nombre del estado (disponible, ocupada, etc.)', type: 'string', example: 'disponible'),
        new OA\Property(property: 'is_available', description: 'Indica si la mesa está libre para asignación', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de registro de la mesa', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', description: 'Fecha y hora de última modificación', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class RestaurantTableResource extends JsonResource
{
    /**
     * Transforma la mesa a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusName = $this->relationLoaded('status') && $this->status
            ? $this->status->name
            : null;

        return [
            'id' => $this->id,
            'number' => (int) $this->number,
            'capacity' => (int) $this->capacity,
            'table_status_id' => $this->table_status_id,
            'status_name' => $statusName,
            'is_available' => $statusName === TableStatus::DISPONIBLE,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
