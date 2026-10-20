<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class PurchaseRequestStatus extends Model
{
    protected $table = 'purchase_request_statuses';

    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const RECHAZADA_SIN_COMPRAR = 'rechazada_sin_comprar';
    public const RECHAZADA = 'rechazada_sin_comprar';
    public const PROCESADA = 'procesada';

    /**
     * @return HasMany<PurchaseRequest, $this>
     */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }
}
