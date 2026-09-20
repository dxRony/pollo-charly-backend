<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id',
    'dish_id',
    'order_item_status_id',
    'quantity',
    'unit_price',
    'subtotal',
    'notes',
])]
class OrderItem extends Model
{
    protected $table = 'order_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * @return BelongsTo<OrderItemStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderItemStatus::class, 'order_item_status_id');
    }

    /**
     * @return HasMany<OrderItemComplement, $this>
     */
    public function complements(): HasMany
    {
        return $this->hasMany(OrderItemComplement::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
