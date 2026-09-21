<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role_id',
    'is_active',
    'two_factor_enabled',
    'two_factor_code',
    'two_factor_expires_at',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_code',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if the user has one of the specified roles.
     */
    public function hasRole(string ...$roles): bool
    {
        return $this->role !== null && in_array($this->role->name, $roles, true);
    }

    /**
     * Check if the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMINISTRADOR);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_user_id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cashier_user_id');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function approvedInventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'approver_user_id');
    }

    /**
     * @return HasMany<SupplyAlert, $this>
     */
    public function supplyAlerts(): HasMany
    {
        return $this->hasMany(SupplyAlert::class);
    }

    /**
     * @return HasMany<PurchaseRequest, $this>
     */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requester_user_id');
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function approvedPurchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'admin_user_id');
    }

    /**
     * @return HasMany<DeliveryIncident, $this>
     */
    public function receivedDeliveryIncidents(): HasMany
    {
        return $this->hasMany(DeliveryIncident::class, 'receiving_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
        ];
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Generate a 6-digit one-time 2FA verification code valid for 5 minutes.
     */
    public function generateTwoFactorCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->two_factor_code = $code;
        $this->two_factor_expires_at = now()->addMinutes(5);
        $this->save();

        return $code;
    }

    /**
     * Reset the 2FA verification code and expiration.
     */
    public function resetTwoFactorCode(): void
    {
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }
}
