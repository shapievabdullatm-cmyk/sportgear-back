<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'warehouse_id',
        'to_warehouse_id',
        'status',
        'completed_at',
        'user_id',
        'completed_by',
        'comment',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items()
    {
        return $this->hasMany(StockDocumentItem::class, 'document_id');
    }

    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getTotalAmountAttribute()
    {
        return $this->items->sum(fn($item) => $item->quantity * ($item->price ?? 0));
    }
}
