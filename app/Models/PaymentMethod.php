<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    public const EFECTIVO = 'efectivo';
    public const TARJETA = 'tarjeta';
    public const TRANSFERENCIA = 'transferencia';

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
