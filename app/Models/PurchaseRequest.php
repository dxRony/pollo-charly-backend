<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['requester_user_id', 'purchase_request_status_id', 'reason'])]
class PurchaseRequest extends Model
{
    protected $table = 'purchase_requests';

    /**
     * @return BelongsTo<User, $this>
     */
    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<PurchaseRequestStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestStatus::class, 'purchase_request_status_id');
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    /**
     * @return HasMany<SupplyAlert, $this>
     */
    public function supplyAlerts(): HasMany
    {
        return $this->hasMany(SupplyAlert::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
