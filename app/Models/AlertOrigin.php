<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class AlertOrigin extends Model
{
    protected $table = 'alert_origins';

    public const MANUAL = 'manual';
    public const AUTOMATIC = 'automatic';

    /**
     * @return HasMany<SupplyAlert, $this>
     */
    public function supplyAlerts(): HasMany
    {
        return $this->hasMany(SupplyAlert::class);
    }
}
