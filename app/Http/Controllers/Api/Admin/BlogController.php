<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Blog\StoreRequest;
use App\Http\Requests\Api\Admin\Blog\UpdateRequest;
use App\Http\Resources\Blog\BlogResource;
use App\Http\Resources\Blog\BlogListResource;
use App\Models\Blog;
use App\Services\BlogService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = Blog::query()->with('category')->latest('created_at');

        // Поиск
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                // Используем translate для преобразования кириллицы и латиницы в нижний регистр
                $upper = 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяabcdefghijklmnopqrstuvwxyz';

                $q->whereRaw("translate(title, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"])
                  ->orWhereRaw("translate(slug, '{$upper}', '{$lower}') LIKE translate(?, '{$upper}', '{$lower}')", ["%{$search}%"]);
            });
        }

        // Пагинация
        if ($request->boolean('paginate', false)) {
            $perPage = $request->integer('per_page', 20);
            $blogs = $query->paginate($perPage);

            return BlogListResource::collection($blogs);
        }

        // Без пагинации
        $blogs = $query->get();
        return BlogListResource::collection($blogs);
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image');
        }

        $blog = BlogService::store($data);

        return BlogResource::make($blog);
    }

    public function show(Blog $blog)
    {
        $blog->load('category');
        return BlogResource::make($blog);
    }

    public function edit(Blog $blog)
    {
        $blog->load('category');
        return response()->json([
            'blog' => BlogResource::make($blog),
        ]);
    }

    public function update(UpdateRequest $request, Blog $blog)
    {
        $data = $request->validated();

        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image');
        }

        $blog = BlogService::update($blog, $data);

        return BlogResource::make($blog);
    }

    public function destroy(Blog $blog)
    {
        BlogService::destroy($blog);
        return response()->json(['message' => 'Блог удалён']);
    }
}