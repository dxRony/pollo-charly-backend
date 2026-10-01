<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'supply_id', 'agreed_price'])]
class SupplierSupply extends Model
{
    protected $table = 'supplier_supplies';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreed_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
