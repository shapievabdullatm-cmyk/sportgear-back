<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Category\PopularCategoryResource;
use App\Models\Category;
use App\Models\PopularCategory;
use Illuminate\Http\Request;

class PopularCategoryController extends Controller
{
    // GET /popular-categories  — публичный, для фронта
    // GET /admin/popular-categories — для админки (одинаковые данные)
    public function index()
    {
        $items = PopularCategory::with('category')
            ->orderBy('position')
            ->get();

        return PopularCategoryResource::collection($items);
    }

    // GET /admin/popular-categories/available
    // Возвращает все категории, которые ещё не добавлены в популярные
    public function available()
    {
        $usedIds = PopularCategory::pluck('category_id');

        $categories = Category::whereNotIn('id', $usedIds)
            ->orderBy('title')
            ->get(['id', 'title', 'slug']);

        return response()->json(['data' => $categories]);
    }

    // POST /admin/popular-categories
    // body: { category_id: 5 }
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id|unique:popular_categories,category_id',
        ]);

        $position = PopularCategory::max('position') + 1;

        $item = PopularCategory::create([
            'category_id' => $request->integer('category_id'),
            'position'    => $position,
        ]);

        $item->load('category');

        return PopularCategoryResource::make($item);
    }

    // DELETE /admin/popular-categories/{popularCategory}
    public function destroy(PopularCategory $popularCategory)
    {
        $popularCategory->delete();

        // Пересчитываем позиции
        PopularCategory::orderBy('position')
            ->get()
            ->each(function (PopularCategory $item, int $idx) {
                $item->update(['position' => $idx]);
            });

        return response()->json(['message' => 'Удалено']);
    }

    // POST /admin/popular-categories/reorder
    // body: { items: [{ id: 1, position: 0 }, ...] }
    public function reorder(Request $request)
    {
        $request->validate([
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:popular_categories,id',
            'items.*.position' => 'required|integer|min:0',
        ]);

        foreach ($request->input('items') as $row) {
            PopularCategory::where('id', $row['id'])
                ->update(['position' => $row['position']]);
        }

        return response()->json(['message' => 'Порядок сохранён']);
    }
}
