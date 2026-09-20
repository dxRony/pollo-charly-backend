<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class OrderStatus extends Model
{
    protected $table = 'order_statuses';

    public const PENDIENTE = 'pendiente';
    public const EN_PREPARACION = 'en_preparacion';
    public const LISTA = 'lista';
    public const ENTREGADA = 'entregada';
    public const CANCELADA = 'cancelada';

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
