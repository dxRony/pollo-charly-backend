<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'sale_id',
    'cash_movement_type_id',
    'cash_movement_category_id',
    'amount',
    'concept',
    'justification',
    'evidence_path',
    'date',
])]
class CashMovement extends Model
{
    protected $table = 'cash_movements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<CashMovementType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CashMovementType::class, 'cash_movement_type_id');
    }

    /**
     * @return BelongsTo<CashMovementCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CashMovementCategory::class, 'cash_movement_category_id');
    }
}
