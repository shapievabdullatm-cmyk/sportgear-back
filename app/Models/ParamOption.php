<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParamOption extends Model
{
    protected $fillable = [
        'param_id',
        'slug',
        'value',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function param()
    {
        return $this->belongsTo(Param::class);
    }
}
