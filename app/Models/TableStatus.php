<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class TableStatus extends Model
{
    protected $table = 'table_statuses';

    public const DISPONIBLE = 'disponible';
    public const OCUPADA = 'ocupada';
    public const MANTENIMIENTO = 'mantenimiento';
    public const INACTIVA = 'inactiva';

    /**
     * @return HasMany<RestaurantTable, $this>
     */
    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }
}
