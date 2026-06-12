<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function destroy(ProductImage $image)
    {
        ProductImageService::destroy($image);

        $image->delete();
        return response()->json([
            'message' => 'deleted'
        ], 200);
    }
}
