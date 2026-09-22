<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'code',
    'restaurant_table_id',
    'waiter_user_id',
    'order_type_id',
    'order_status_id',
    'cancellation_reason',
    'notes',
    'preparation_start_time',
])]
class Order extends Model
{
    protected $table = 'orders';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preparation_start_time' => 'datetime',
        ];
    }

    /**
     * Determina si la comanda tiene restringida la eliminación de platillos (solo adición).
     * Condición: orden en preparación, preparation_start_time no nulo, o tiempo límite superado.
     */
    public function isModificationRestricted(): bool
    {
        $timeLimitMinutes = (int) config('orders.modification_time_limit_minutes', 5);

        // Si ya inició preparación (por timestamp o por estado)
        if ($this->preparation_start_time !== null) {
            return true;
        }

        $statusName = $this->relationLoaded('status') && $this->status ? $this->status->name : null;
        if ($statusName === OrderStatus::EN_PREPARACION || $statusName === OrderStatus::LISTA || $statusName === OrderStatus::ENTREGADA) {
            return true;
        }

        // Si superó el tiempo límite desde su registro
        if ($this->created_at !== null && now()->greaterThan($this->created_at->copy()->addMinutes($timeLimitMinutes))) {
            return true;
        }

        return false;
    }

    /**
     * Devuelve el motivo descriptivo por el cual la comanda tiene la modificación restringida a solo adición.
     */
    public function getModificationRestrictionReason(): ?string
    {
        $timeLimitMinutes = (int) config('orders.modification_time_limit_minutes', 5);

        $statusName = $this->relationLoaded('status') && $this->status ? $this->status->name : null;

        // El estado (Lista/Entregada) es más específico que el timestamp genérico de
        // preparation_start_time, que nunca se limpia una vez iniciada la preparación —
        // por eso se evalúa primero, o el mensaje de "en preparación" nunca se actualizaría.
        if ($statusName === OrderStatus::LISTA || $statusName === OrderStatus::ENTREGADA) {
            return 'La comanda ya finalizó su preparación en cocina. Únicamente se permite la adición de productos.';
        }

        if ($this->preparation_start_time !== null || $statusName === OrderStatus::EN_PREPARACION) {
            return 'La comanda ya está en preparación en cocina. Únicamente se permite la adición de productos.';
        }

        if ($this->created_at !== null && now()->greaterThan($this->created_at->copy()->addMinutes($timeLimitMinutes))) {
            return "Se ha superado el tiempo límite de modificación libre ({$timeLimitMinutes} minutos). Únicamente se permite la adición de productos.";
        }

        return null;
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_user_id');
    }

    /**
     * @return BelongsTo<OrderType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(OrderType::class, 'order_type_id');
    }

    /**
     * @return BelongsTo<OrderStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Sale, $this>
     */
    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
