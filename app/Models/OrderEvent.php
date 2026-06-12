<?php

namespace App\Models;

use App\Enums\Order\OrderEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'type',
        'data',
        'created_at',
    ];

    protected $casts = [
        'type'       => OrderEventType::class,
        'data'       => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}