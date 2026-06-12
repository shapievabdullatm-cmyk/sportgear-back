<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'related_warehouse_id',
        'reason',
        'comment',
        'user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function relatedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'related_warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
