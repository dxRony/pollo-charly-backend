<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ComplementSupplyItemResource',
    title: 'Complement Supply Item',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', type: 'integer', example: 2),
        new OA\Property(property: 'supply_name', type: 'string', example: 'Queso Mozzarella'),
        new OA\Property(property: 'measurement_unit', type: 'string', example: 'kg'),
        new OA\Property(property: 'required_quantity', type: 'number', format: 'float', example: 0.10),
    ]
)]
#[OA\Schema(
    schema: 'ComplementResource',
    title: 'Complement Resource',
    description: 'Información completa del complemento y sus insumos requeridos por porción',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Extra queso'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Porción extra de queso fundido'),
        new OA\Property(property: 'extra_price', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'supplies', type: 'array', items: new OA\Items(ref: '#/components/schemas/ComplementSupplyItemResource')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class ComplementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'extra_price' => (float) $this->extra_price,
            'is_active' => (bool) $this->is_active,
            'supplies' => $this->relationLoaded('complementSupplies') ? $this->complementSupplies->map(fn ($cs) => [
                'id' => $cs->id,
                'supply_id' => $cs->supply_id,
                'supply_name' => $cs->supply?->name,
                'measurement_unit' => $cs->supply?->measurementUnit?->abbreviation ?? $cs->supply?->measurementUnit?->name,
                'required_quantity' => (float) $cs->required_quantity,
            ]) : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
