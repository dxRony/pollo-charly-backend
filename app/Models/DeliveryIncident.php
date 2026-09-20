<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'supplier_id',
    'receiving_user_id',
    'delivery_incident_type_id',
    'delivery_incident_status_id',
    'description',
    'evidence_path',
])]
class DeliveryIncident extends Model
{
    protected $table = 'delivery_incidents';

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiving_user_id');
    }

    /**
     * @return BelongsTo<DeliveryIncidentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DeliveryIncidentType::class, 'delivery_incident_type_id');
    }

    /**
     * @return BelongsTo<DeliveryIncidentStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(DeliveryIncidentStatus::class, 'delivery_incident_status_id');
    }
}
