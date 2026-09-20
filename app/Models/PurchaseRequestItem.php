<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_request_id',
    'supply_id',
    'suggested_quantity',
    'approved_quantity',
])]
class PurchaseRequestItem extends Model
{
    protected $table = 'purchase_request_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suggested_quantity' => 'decimal:2',
            'approved_quantity' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
