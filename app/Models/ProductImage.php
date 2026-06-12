<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'is_main', 'sort_order'];

    protected $casts = [
        'is_main'    => 'boolean',
        'sort_order' => 'integer',
    ];
    protected $appends = ['url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->path);
    }
}
