<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DeliveryDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin DeliveryDay
 */
#[OA\Schema(
    schema: 'DeliveryDayResource',
    title: 'Delivery Day Resource',
    description: 'Día de entrega de proveedores',
    properties: [
        new OA\Property(property: 'id', description: 'ID único del día', type: 'integer', example: 1),
        new OA\Property(property: 'name', description: 'Nombre del día (ej. Lunes)', type: 'string', example: 'Lunes'),
    ]
)]
class DeliveryDayResource extends JsonResource
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
