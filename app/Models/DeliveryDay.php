<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class DeliveryDay extends Model
{
    protected $table = 'delivery_days';

    public const LUNES = 'Lunes';
    public const MARTES = 'Martes';
    public const MIERCOLES = 'Miércoles';
    public const JUEVES = 'Jueves';
    public const VIERNES = 'Viernes';
    public const SABADO = 'Sábado';
    public const DOMINGO = 'Domingo';

    /**
     * @return BelongsToMany<Supplier, $this>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_delivery_days');
    }
}
