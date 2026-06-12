<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductParamValue extends Model
{
    protected $fillable = [
        'product_id', 'param_id',
        'value_string', 'value_text', 'value_int', 'value_float',
        'param_option_id',
    ];

    protected $casts = [
        'value_int'   => 'integer',
        'value_float' => 'float',
    ];

    public function param(): BelongsTo
    {
        return $this->belongsTo(Param::class);
    }

    public function paramOption(): BelongsTo
    {
        return $this->belongsTo(ParamOption::class);
    }

    // Alias для удобства в поиске
    public function option(): BelongsTo
    {
        return $this->paramOption();
    }
}
