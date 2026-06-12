<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'full_address',
        'lat',
        'lon',
        'city',
        'street',
        'house',
        'apartment',
        'entrance',
        'floor',
        'intercom',
        'comment',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'lat'        => 'float',
        'lon'        => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}