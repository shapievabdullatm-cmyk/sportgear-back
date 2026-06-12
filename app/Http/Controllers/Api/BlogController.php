<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blog\BlogResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::published()->with('category')->latest('published_at');

        if ($request->filled('category')) {
            $category = BlogCategory::where('slug', $request->string('category'))->first();
            if ($category) {
                $query->where('blog_category_id', $category->id);
            }
        }

        return BlogResource::collection($query->paginate(12));
    }

    public function latest()
    {
        $blogs = Blog::published()
            ->with('category')
            ->latest('published_at')
            ->limit(3)
            ->get();

        return BlogResource::collection($blogs);
    }

    public function show(Blog $blog)
    {
        if (!$blog->is_published || !$blog->published_at || $blog->published_at->isFuture()) {
            abort(404);
        }

        $blog->load('category');

        return BlogResource::make($blog);
    }
}