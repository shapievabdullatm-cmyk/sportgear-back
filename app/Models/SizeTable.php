<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeTable extends Model
{
    protected $fillable = [
        'name',
        'headers',
        'rows',
        'sort',
        'is_active',
    ];

    protected $casts = [
        'headers' => 'array',
        'rows' => 'array',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
