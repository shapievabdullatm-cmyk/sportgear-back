<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\BlogCategory\StoreRequest;
use App\Http\Requests\Api\Admin\BlogCategory\UpdateRequest;
use App\Http\Resources\Blog\BlogCategoryResource;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlogCategoryController extends Controller
{
    public function index()
    {
        $categories = BlogCategory::orderBy('sort_order')->orderBy('id')->get();

        return BlogCategoryResource::collection($categories);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = BlogCategory::max('sort_order') + 1;
        }

        $category = BlogCategory::create($data);

        return BlogCategoryResource::make($category);
    }

    public function update(UpdateRequest $request, BlogCategory $blogCategory)
    {
        $blogCategory->update($request->validated());

        return BlogCategoryResource::make($blogCategory->fresh());
    }

    public function destroy(BlogCategory $blogCategory)
    {
        $blogCategory->delete();

        return response()->json(['message' => 'Категория удалена']);
    }

    public function reorder(Request $request)
    {
        $ids = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:blog_categories,id',
        ])['ids'];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $order => $id) {
                BlogCategory::where('id', $id)->update(['sort_order' => $order]);
            }
        });

        return response()->json(['message' => 'Порядок сохранён']);
    }
}