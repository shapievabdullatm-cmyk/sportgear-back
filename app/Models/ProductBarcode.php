<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBarcode extends Model
{
    protected $fillable = [
        'product_id',
        'barcode',
        'type',
    ];

    protected $appends = ['is_duplicate'];

    protected static function booted(): void
    {
        $resync = fn(self $barcode) => $barcode->product?->searchable();
        static::saved($resync);
        static::deleted($resync);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getIsDuplicateAttribute(): bool
    {
        return self::where('barcode', $this->barcode)
            ->where('id', '!=', $this->id)
            ->exists();
    }
}
