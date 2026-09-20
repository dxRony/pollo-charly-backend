<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class AdjustmentStatusType extends Model
{
    protected $table = 'adjustment_status_types';

    public const APROBADO = 'aprobado';
    public const PENDIENTE_APROBACION = 'pendiente_aprobacion';
    public const RECHAZADO = 'rechazado';

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
