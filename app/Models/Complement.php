<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'extra_price', 'is_active'])]
class Complement extends Model
{
    protected $table = 'complements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ComplementSupply, $this>
     */
    public function complementSupplies(): HasMany
    {
        return $this->hasMany(ComplementSupply::class);
    }

    /**
     * @return BelongsToMany<Supply, $this>
     */
    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'complement_supplies')
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
     * @return BelongsToMany<Dish, $this>
     */
    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_complements')
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrderItemComplement, $this>
     */
    public function orderItemComplements(): HasMany
    {
        return $this->hasMany(OrderItemComplement::class);
    }

    /**
     * Check if the complement is used in active orders (pending or in preparation).
     */
    public function hasActiveOrders(): bool
    {
        return $this->orderItemComplements()
            ->whereHas('orderItem.order.status', function ($query) {
                $query->whereIn('name', [OrderStatus::PENDIENTE, OrderStatus::EN_PREPARACION]);
            })
            ->exists();
    }
}
