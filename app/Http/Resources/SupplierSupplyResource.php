<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SupplierSupply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin SupplierSupply
 */
#[OA\Schema(
    schema: 'SupplierSupplyResource',
    title: 'Supplier Supply Resource',
    description: 'Insumo provisto por el proveedor con precio acordado',
    properties: [
        new OA\Property(property: 'id', description: 'ID del registro de insumo del proveedor', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', description: 'ID del insumo en catálogo', type: 'integer', example: 2),
        new OA\Property(property: 'name', description: 'Nombre del insumo', type: 'string', example: 'Pechuga de Pollo Fresca'),
        new OA\Property(property: 'code', description: 'Código del insumo', type: 'string', example: 'INS-0001'),
        new OA\Property(property: 'measurement_unit', description: 'Unidad de medida', type: 'string', example: 'Kilogramos'),
        new OA\Property(property: 'agreed_price', description: 'Precio acordado por unidad', type: 'number', example: 34.50),
        new OA\Property(property: 'unit_cost', description: 'Costo unitario actual en catálogo', type: 'number', example: 35.00),
    ]
)]
class SupplierSupplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_id' => $this->supply_id,
            'name' => $this->supply?->name,
            'code' => $this->supply?->code,
            'measurement_unit' => $this->supply?->measurementUnit?->name,
            'agreed_price' => (float) $this->agreed_price,
            'unit_cost' => $this->supply ? (float) $this->supply->unit_cost : null,
        ];
    }
}
