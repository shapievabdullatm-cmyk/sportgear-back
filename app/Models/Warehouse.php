<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_warehouse')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps();
    }
}
