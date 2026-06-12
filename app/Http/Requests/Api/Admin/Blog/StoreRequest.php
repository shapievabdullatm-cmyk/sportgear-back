<?php

namespace App\Http\Requests\Api\Admin\Blog;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blog_category_id' => 'nullable|integer|exists:blog_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blogs,slug',
            'banner_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:1000',
            'meta_keywords' => 'nullable|string|max:1000',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Заголовок обязателен',
            'title.max' => 'Заголовок не должен превышать 255 символов',
            'slug.unique' => 'Такой slug уже существует',
            'banner_image.image' => 'Файл должен быть изображением',
            'banner_image.mimes' => 'Допустимые форматы: JPG, PNG, WEBP',
            'banner_image.max' => 'Размер изображения не должен превышать 5 МБ',
            'content.string' => 'Контент должен быть строкой',
        ];
    }
}