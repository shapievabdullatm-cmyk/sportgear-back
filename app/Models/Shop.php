<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shop extends Model
{
    protected $fillable = [
        'external_id',
        'slug',
        'name',
        'description',
        'address',
        'city',
        'latitude',
        'longitude',
        'phone',
        'email',
        'working_hours',
        'metadata',
        'is_active',
        'sort_order',
        'pickup_enabled',
        'pickup_min_lead_minutes',
        'pickup_slot_minutes',
        'pickup_max_per_slot',
        'pickup_advance_days',
    ];

    protected $casts = [
        'working_hours'           => 'array',
        'metadata'                => 'array',
        'is_active'               => 'boolean',
        'latitude'                => 'float',
        'longitude'               => 'float',
        'sort_order'              => 'integer',
        'pickup_enabled'          => 'boolean',
        'pickup_min_lead_minutes' => 'integer',
        'pickup_slot_minutes'     => 'integer',
        'pickup_max_per_slot'     => 'integer',
        'pickup_advance_days'     => 'integer',
    ];

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'shop_warehouse')
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}