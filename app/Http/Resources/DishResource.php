<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DishCategoryResource',
    title: 'Dish Category',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Pollos'),
    ]
)]
#[OA\Schema(
    schema: 'DishRecipeItemResource',
    title: 'Dish Recipe Item',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', type: 'integer', example: 5),
        new OA\Property(property: 'supply_name', type: 'string', example: 'Pechuga de Pollo'),
        new OA\Property(property: 'measurement_unit', type: 'string', example: 'kg'),
        new OA\Property(property: 'required_quantity', type: 'number', format: 'float', example: 0.50),
    ]
)]
#[OA\Schema(
    schema: 'DishComplementItemResource',
    title: 'Dish Complement Item',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'name', type: 'string', example: 'Papas Fritas'),
        new OA\Property(property: 'extra_price', type: 'number', format: 'float', example: 15.00),
    ]
)]
#[OA\Schema(
    schema: 'DishResource',
    title: 'Dish Resource',
    description: 'Información completa del platillo con su receta y complementos asociados',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Pollo Frito Familiar'),
        new OA\Property(property: 'category_id', type: 'integer', example: 1),
        new OA\Property(property: 'category', ref: '#/components/schemas/DishCategoryResource', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: '8 piezas de pollo frito crujiente'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 125.00),
        new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'https://cdn.pollocharly.com/dishes/familiar.jpg'),
        new OA\Property(property: 'is_daily_menu', type: 'boolean', example: false),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'recipes', type: 'array', items: new OA\Items(ref: '#/components/schemas/DishRecipeItemResource')),
        new OA\Property(property: 'complements', type: 'array', items: new OA\Items(ref: '#/components/schemas/DishComplementItemResource')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true, example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class DishResource extends JsonResource
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
            'category_id' => $this->category_id,
            'category' => $this->relationLoaded('category') && $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'description' => $this->description,
            'price' => (float) $this->price,
            'image_url' => $this->image_url,
            'is_daily_menu' => (bool) $this->is_daily_menu,
            'is_active' => (bool) $this->is_active,
            'recipes' => $this->relationLoaded('recipes') ? $this->recipes->map(fn ($r) => [
                'id' => $r->id,
                'supply_id' => $r->supply_id,
                'supply_name' => $r->supply?->name,
                'measurement_unit' => $r->supply?->measurementUnit?->abbreviation ?? $r->supply?->measurementUnit?->name,
                'required_quantity' => (float) $r->required_quantity,
            ]) : [],
            'complements' => $this->relationLoaded('complements') ? $this->complements->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'extra_price' => (float) $c->extra_price,
            ]) : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
