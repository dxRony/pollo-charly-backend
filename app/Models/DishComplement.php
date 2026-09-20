<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dish_id', 'complement_id'])]
class DishComplement extends Model
{
    protected $table = 'dish_complements';

    /**
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * @return BelongsTo<Complement, $this>
     */
    public function complement(): BelongsTo
    {
        return $this->belongsTo(Complement::class);
    }
}
