<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'abbreviation'])]
class MeasurementUnit extends Model
{
    protected $table = 'measurement_units';

    public const KG = 'Kilogramo';
    public const G = 'Gramo';
    public const L = 'Litro';
    public const ML = 'Mililitro';
    public const U = 'Unidad';
    public const PORC = 'Porción';
    public const PAQ = 'Paquete';

    /**
     * @return HasMany<Supply, $this>
     */
    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }
}
