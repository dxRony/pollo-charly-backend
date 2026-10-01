<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supply_id',
    'alert_origin_id',
    'alert_status_id',
    'user_id',
    'purchase_request_id',
    'notes',
])]
class SupplyAlert extends Model
{
    protected $table = 'supply_alerts';

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    /**
     * @return BelongsTo<AlertOrigin, $this>
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(AlertOrigin::class, 'alert_origin_id');
    }

    /**
     * @return BelongsTo<AlertStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AlertStatus::class, 'alert_status_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
