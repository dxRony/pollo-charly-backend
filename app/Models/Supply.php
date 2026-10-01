<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['measurement_unit_id', 'code', 'name', 'current_stock', 'minimum_stock', 'unit_cost', 'is_active'])]
class Supply extends Model
{
    protected $table = 'supplies';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MeasurementUnit, $this>
     */
    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    /**
     * @return HasMany<DishRecipe, $this>
     */
    public function dishRecipes(): HasMany
    {
        return $this->hasMany(DishRecipe::class);
    }

    /**
     * @return BelongsToMany<Dish, $this>
     */
    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'dish_recipes')
            ->withPivot('required_quantity')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ComplementSupply, $this>
     */
    public function complementSupplies(): HasMany
    {
        return $this->hasMany(ComplementSupply::class);
    }

    /**
     * @return BelongsToMany<Complement, $this>
     */
    public function complements(): BelongsToMany
    {
        return $this->belongsToMany(Complement::class, 'complement_supplies')
            ->withPivot('required_quantity')
            ->withTimestamps();
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<SupplyAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(SupplyAlert::class);
    }

    /**
     * @return HasMany<SupplierSupply, $this>
     */
    public function supplierSupplies(): HasMany
    {
        return $this->hasMany(SupplierSupply::class);
    }

    /**
     * @return BelongsToMany<Supplier, $this>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_supplies')
            ->withPivot('agreed_price')
            ->withTimestamps();
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function purchaseRequestItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
