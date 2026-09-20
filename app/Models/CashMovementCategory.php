<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class CashMovementCategory extends Model
{
    protected $table = 'cash_movement_categories';

    public const VENTA = 'venta';
    public const PAGO_PROVEEDOR = 'pago_proveedor';
    public const COMPRA_INSUMO = 'compra_insumo';
    public const RETIRO = 'retiro';
    public const APORTE = 'aporte';
    public const AJUSTE = 'ajuste';

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
