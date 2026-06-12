<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_title',
        'product_slug',
        'product_image',
        'product_size',
        'price',
        'quantity',
        'total',
        'reserved_warehouse_id',
    ];

    protected $casts = [
        'price'    => 'float',
        'quantity' => 'integer',
        'total'    => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reservedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'reserved_warehouse_id');
    }
}