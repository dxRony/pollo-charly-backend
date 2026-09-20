<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_name',
    'contact_name',
    'phone',
    'email',
    'address',
    'is_active',
])]
class Supplier extends Model
{
    protected $table = 'suppliers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<DeliveryDay, $this>
     */
    public function deliveryDays(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryDay::class, 'supplier_delivery_days')
            ->withTimestamps();
    }

    /**
     * @return HasMany<SupplierSupply, $this>
     */
    public function supplierSupplies(): HasMany
    {
        return $this->hasMany(SupplierSupply::class);
    }

    /**
     * @return BelongsToMany<Supply, $this>
     */
    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'supplier_supplies')
            ->withPivot('agreed_price')
            ->withTimestamps();
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<DeliveryIncident, $this>
     */
    public function deliveryIncidents(): HasMany
    {
        return $this->hasMany(DeliveryIncident::class);
    }
}
