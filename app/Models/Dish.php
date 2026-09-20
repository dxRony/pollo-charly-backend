<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'description', 'price', 'image_url', 'is_daily_menu', 'is_active'])]
class Dish extends Model
{
    protected $table = 'dishes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_daily_menu' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<DishRecipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(DishRecipe::class);
    }

    /**
     * @return BelongsToMany<Supply, $this>
     */
    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'dish_recipes')
            ->withPivot('required_quantity')
            ->withTimestamps();
    }

    /**
     * @return HasMany<DishComplement, $this>
     */
    public function dishComplements(): HasMany
    {
        return $this->hasMany(DishComplement::class);
    }

    /**
     * @return BelongsToMany<Complement, $this>
     */
    public function complements(): BelongsToMany
    {
        return $this->belongsToMany(Complement::class, 'dish_complements')
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
