<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class DeliveryIncidentStatus extends Model
{
    protected $table = 'delivery_incident_statuses';

    public const REPORTADA = 'reportada';
    public const EN_CORRECCION = 'en_correccion';
    public const RESUELTA = 'resuelta';

    /**
     * @return HasMany<DeliveryIncident, $this>
     */
    public function deliveryIncidents(): HasMany
    {
        return $this->hasMany(DeliveryIncident::class);
    }
}
