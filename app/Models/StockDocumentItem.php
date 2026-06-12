<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockDocumentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'product_id',
        'quantity',
        'price',
        'comment',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function document()
    {
        return $this->belongsTo(StockDocument::class, 'document_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalAttribute()
    {
        return $this->quantity * ($this->price ?? 0);
    }
}
