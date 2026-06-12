<?php

namespace App\Models;

use App\Enums\Order\DeliveryMethod;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'number',
        'user_id',
        'status',
        'delivery_method',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_cost',
        'total',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shop_id',
        'pickup_slot_at',
        'address_full',
        'address_lat',
        'address_lon',
        'city',
        'street',
        'house',
        'apartment',
        'entrance',
        'floor',
        'intercom',
        'cdek_pvz_code',
        'russian_post_index',
        'tracking_number',
        'comment',
        'admin_comment',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status'          => OrderStatus::class,
        'delivery_method' => DeliveryMethod::class,
        'payment_method'  => PaymentMethod::class,
        'payment_status'  => PaymentStatus::class,
        'subtotal'        => 'float',
        'delivery_cost'   => 'float',
        'total'           => 'float',
        'address_lat'     => 'float',
        'address_lon'     => 'float',
        'pickup_slot_at'  => 'datetime',
        'confirmed_at'    => 'datetime',
        'shipped_at'      => 'datetime',
        'delivered_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at');
    }
}