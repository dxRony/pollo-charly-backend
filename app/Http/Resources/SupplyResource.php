<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SupplyResource',
    title: 'Supply Resource',
    description: 'Información completa del producto o insumo de almacén',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único del insumo', type: 'integer', example: 1),
        new OA\Property(property: 'code', description: 'Código único de identificación del insumo', type: 'string', example: 'INS-0001'),
        new OA\Property(property: 'name', description: 'Nombre descriptivo del insumo', type: 'string', example: 'Pechuga de Pollo'),
        new OA\Property(property: 'measurement_unit_id', description: 'ID de la unidad de medida', type: 'integer', example: 1),
        new OA\Property(property: 'measurement_unit', ref: '#/components/schemas/MeasurementUnitResource', nullable: true),
        new OA\Property(property: 'current_stock', description: 'Existencia actual en inventario físico', type: 'number', format: 'float', example: 45.50),
        new OA\Property(property: 'minimum_stock', description: 'Cantidad de referencia mínima requerida en almacén', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'unit_cost', description: 'Costo unitario de adquisición', type: 'number', format: 'float', example: 32.50),
        new OA\Property(property: 'is_active', description: 'Estado de disponibilidad del insumo', type: 'boolean', example: true),
        new OA\Property(property: 'is_low_stock', description: 'Indica si la existencia actual está por debajo o igual al stock mínimo', type: 'boolean', example: false),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de creación del registro', type: 'string', format: 'date-time', nullable: true, example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class SupplyResource extends JsonResource
{
    /**
     * Transforma el insumo a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentStock = (float) $this->current_stock;
        $minimumStock = (float) $this->minimum_stock;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'measurement_unit_id' => $this->measurement_unit_id,
            'measurement_unit' => $this->relationLoaded('measurementUnit') && $this->measurementUnit
                ? new MeasurementUnitResource($this->measurementUnit)
                : null,
            'current_stock' => $currentStock,
            'minimum_stock' => $minimumStock,
            'unit_cost' => (float) $this->unit_cost,
            'is_active' => (bool) $this->is_active,
            'is_low_stock' => $currentStock <= $minimumStock,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
