<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blog\BlogCategoryResource;
use App\Models\BlogCategory;

class BlogCategoryController extends Controller
{
    public function index()
    {
        $categories = BlogCategory::orderBy('sort_order')->orderBy('id')->get();

        return BlogCategoryResource::collection($categories);
    }
}