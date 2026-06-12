<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected $fillable = ['title', 'link', 'image', 'mobile_image', 'position'];
}
