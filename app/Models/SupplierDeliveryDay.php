<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'delivery_day_id'])]
class SupplierDeliveryDay extends Model
{
    protected $table = 'supplier_delivery_days';

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<DeliveryDay, $this>
     */
    public function deliveryDay(): BelongsTo
    {
        return $this->belongsTo(DeliveryDay::class);
    }
}
