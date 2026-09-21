<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class InventoryMovementType extends Model
{
    protected $table = 'inventory_movement_types';

    public const COMPRA_ENTRADA = 'compra_entrada';
    public const CONSUMO_VENTA = 'consumo_venta';
    public const MERMA_DANO = 'merma_dano';
    public const AJUSTE_INVENTARIO = 'ajuste_inventario';
    public const CANCELACION_PEDIDO = 'cancelacion_pedido';
    public const AJUSTE_MERMA = 'ajuste_merma';
    public const AJUSTE_SOBRANTE = 'ajuste_sobrante';
    public const AJUSTE_FALTANTE = 'ajuste_faltante';
    public const AJUSTE_CONTEO_FISICO = 'ajuste_conteo_fisico';

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
