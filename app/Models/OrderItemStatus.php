<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class OrderItemStatus extends Model
{
    protected $table = 'order_item_statuses';

    public const PENDIENTE = 'pendiente';
    public const EN_PREPARACION = 'en_preparacion';
    public const LISTO = 'listo';
    public const CANCELADO = 'cancelado';
    public const ELIMINADO = 'eliminado';

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
