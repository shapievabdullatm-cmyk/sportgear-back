<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCollection extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProductCollectionItem::class, 'collection_id')
            ->orderBy('position');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }
}
