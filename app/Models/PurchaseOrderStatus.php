<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class PurchaseOrderStatus extends Model
{
    protected $table = 'purchase_order_statuses';

    public const SOLICITADA = 'solicitada';
    public const RECIBIDA_COMPLETA = 'recibida_completa';
    public const RECIBIDA_INCOMPLETA = 'recibida_incompleta';
    public const CANCELADA = 'cancelada';

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
