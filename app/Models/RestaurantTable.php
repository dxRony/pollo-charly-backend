<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['table_status_id', 'number', 'capacity'])]
class RestaurantTable extends Model
{
    protected $table = 'restaurant_tables';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TableStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TableStatus::class, 'table_status_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Determina si la mesa tiene comandas activas pendientes, en preparación o listas.
     */
    public function hasActiveOrders(): bool
    {
        return $this->orders()
            ->whereHas('status', function ($query) {
                $query->whereIn('name', [
                    OrderStatus::PENDIENTE,
                    OrderStatus::EN_PREPARACION,
                    OrderStatus::LISTA,
                ]);
            })
            ->exists();
    }
}
