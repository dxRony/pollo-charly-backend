<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'supply_id',
    'ordered_quantity',
    'received_quantity',
    'unit_price',
    'subtotal',
])]
class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
