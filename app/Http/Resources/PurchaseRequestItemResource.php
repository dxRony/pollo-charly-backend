<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PurchaseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin PurchaseRequestItem
 */
#[OA\Schema(
    schema: 'PurchaseRequestItemResource',
    title: 'Purchase Request Item Resource',
    description: 'Ítem de una solicitud de compra',
    properties: [
        new OA\Property(property: 'id', description: 'ID del ítem', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', description: 'ID del insumo', type: 'integer', example: 2),
        new OA\Property(property: 'supply_name', description: 'Nombre del insumo', type: 'string', example: 'Pechuga de Pollo'),
        new OA\Property(property: 'supply_code', description: 'Código del insumo', type: 'string', example: 'INS-001'),
        new OA\Property(property: 'measurement_unit', description: 'Unidad de medida', type: 'string', example: 'Kilogramos'),
        new OA\Property(property: 'suggested_quantity', description: 'Cantidad sugerida por la alerta o usuario', type: 'number', example: 25.00),
        new OA\Property(property: 'approved_quantity', description: 'Cantidad aprobada', type: 'number', nullable: true, example: 20.00),
        new OA\Property(property: 'current_unit_cost', description: 'Costo unitario referencial del catálogo', type: 'number', example: 35.00),
    ]
)]
class PurchaseRequestItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_id' => $this->supply_id,
            'supply_name' => $this->supply?->name,
            'supply_code' => $this->supply?->code,
            'measurement_unit' => $this->supply?->measurementUnit?->name ?? $this->supply?->measurementUnit?->abbreviation,
            'suggested_quantity' => (float) $this->suggested_quantity,
            'approved_quantity' => $this->approved_quantity !== null ? (float) $this->approved_quantity : null,
            'current_unit_cost' => (float) ($this->supply?->unit_cost ?? 0.0),
        ];
    }
}
