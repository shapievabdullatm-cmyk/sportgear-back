<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductParamOptionValue extends Model
{
    protected $fillable = ['product_id', 'param_id', 'param_option_id'];

    public function param(): BelongsTo
    {
        return $this->belongsTo(Param::class);
    }

    public function paramOption(): BelongsTo
    {
        return $this->belongsTo(ParamOption::class);
    }
}
