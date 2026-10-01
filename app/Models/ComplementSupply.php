<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['complement_id', 'supply_id', 'required_quantity'])]
class ComplementSupply extends Model
{
    protected $table = 'complement_supplies';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_quantity' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Complement, $this>
     */
    public function complement(): BelongsTo
    {
        return $this->belongsTo(Complement::class);
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
