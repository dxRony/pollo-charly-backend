<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'description', 'price', 'image_url', 'is_daily_menu', 'is_active'])]
class Dish extends Model
{
    protected $table = 'dishes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_daily_menu' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<DishRecipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(DishRecipe::class);
    }

    /**
     * @return BelongsToMany<Supply, $this>
     */
    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'dish_recipes')
            ->withPivot('required_quantity')
            ->withTimestamps();
    }

    /**
     * @return HasMany<DishComplement, $this>
     */
    public function dishComplements(): HasMany
    {
        return $this->hasMany(DishComplement::class);
    }

    /**
     * @return BelongsToMany<Complement, $this>
     */
    public function complements(): BelongsToMany
    {
        return $this->belongsToMany(Complement::class, 'dish_complements')
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Check if the dish has active orders (in status 'pendiente' or 'en_preparacion').
     */
    public function hasActiveOrders(): bool
    {
        return $this->orderItems()
            ->whereHas('order.status', function ($query) {
                $query->whereIn('name', [OrderStatus::PENDIENTE, OrderStatus::EN_PREPARACION]);
            })
            ->exists();
    }

    /**
     * Check if all required supplies in the recipe are available in current inventory stock.
     *
     * @return array{available: bool, insufficient_supplies: array<int, array{supply_id: int, name: string, required: float, current_stock: float, unit: string}>}
     */
    public function checkSupplyAvailability(float $portions = 1.0): array
    {
        $insufficient = [];
        $this->loadMissing('recipes.supply.measurementUnit');

        foreach ($this->recipes as $recipe) {
            $supply = $recipe->supply;
            if (! $supply) {
                continue;
            }

            $needed = (float) $recipe->required_quantity * $portions;
            $stock = (float) $supply->current_stock;

            if ($stock < $needed) {
                $insufficient[] = [
                    'supply_id' => $supply->id,
                    'name' => $supply->name,
                    'required' => $needed,
                    'current_stock' => $stock,
                    'unit' => $supply->measurementUnit?->abbreviation ?? $supply->measurementUnit?->name ?? '',
                ];
            }
        }

        return [
            'available' => count($insufficient) === 0,
            'insufficient_supplies' => $insufficient,
        ];
    }

    /**
     * Determine if the dish has sufficient supplies available for preparation.
     */
    public function hasSufficientSupplies(): bool
    {
        return $this->checkSupplyAvailability()['available'];
    }
}
