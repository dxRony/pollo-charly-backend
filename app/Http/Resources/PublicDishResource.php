<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PublicDishComplementResource',
    title: 'Public Dish Complement',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'name', type: 'string', example: 'Papas Fritas'),
        new OA\Property(property: 'extra_price', type: 'number', format: 'float', example: 15.00),
    ]
)]
#[OA\Schema(
    schema: 'PublicDishResource',
    title: 'Public Dish Resource',
    description: 'Datos de un platillo aptos para el público (landing page). No incluye receta, insumos ni datos internos.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Pollo Frito Familiar'),
        new OA\Property(property: 'category', ref: '#/components/schemas/DishCategoryResource', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: '8 piezas de pollo frito crujiente'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 125.00),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'https://res.cloudinary.com/demo/image/upload/v1/pollo-charly/dishes/abc123.jpg'),
        new OA\Property(property: 'complements', type: 'array', items: new OA\Items(ref: '#/components/schemas/PublicDishComplementResource')),
    ]
)]
class PublicDishResource extends JsonResource
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
            'category' => $this->relationLoaded('category') && $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'description' => $this->description,
            'price' => (float) $this->price,
            'image_url' => $this->image_url,
            'complements' => $this->relationLoaded('complements')
                ? $this->complements->map(fn ($complement) => [
                    'id' => $complement->id,
                    'name' => $complement->name,
                    'extra_price' => (float) $complement->extra_price,
                ])->values()
                : [],
        ];
    }
}
