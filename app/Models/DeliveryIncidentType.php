<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class DeliveryIncidentType extends Model
{
    protected $table = 'delivery_incident_types';

    public const PESO_INCOMPLETO = 'peso_incompleto';
    public const PRODUCTO_DANADO = 'producto_danado';
    public const CALIDAD_DEFICIENTE = 'calidad_deficiente';
    public const PRODUCTO_EQUIVOCADO = 'producto_equivocado';
    public const OTRO = 'otro';

    /**
     * @return HasMany<DeliveryIncident, $this>
     */
    public function deliveryIncidents(): HasMany
    {
        return $this->hasMany(DeliveryIncident::class);
    }
}
