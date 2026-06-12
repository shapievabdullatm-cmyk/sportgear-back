<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCollectionItem extends Model
{
    protected $fillable = [
        'collection_id',
        'product_id',
        'position',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'collection_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
