<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supply_id',
    'inventory_movement_type_id',
    'user_id',
    'order_id',
    'order_item_id',
    'purchase_order_id',
    'adjustment_status_type_id',
    'approver_user_id',
    'quantity',
    'previous_stock',
    'new_stock',
    'reason',
])]
class InventoryMovement extends Model
{
    protected $table = 'inventory_movements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'previous_stock' => 'decimal:2',
            'new_stock' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    /**
     * @return BelongsTo<InventoryMovementType, $this>
     */
    public function movementType(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementType::class, 'inventory_movement_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<AdjustmentStatusType, $this>
     */
    public function adjustmentStatus(): BelongsTo
    {
        return $this->belongsTo(AdjustmentStatusType::class, 'adjustment_status_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
