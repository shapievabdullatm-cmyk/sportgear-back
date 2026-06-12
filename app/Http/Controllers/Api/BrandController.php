<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Brand\BrandResource;
use App\Models\Brand;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::orderBy('name')->get();

        return BrandResource::collection($brands);
    }

    public function show(Brand $brand)
    {
        return BrandResource::make($brand);
    }
}