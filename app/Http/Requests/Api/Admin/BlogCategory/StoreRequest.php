<?php

namespace App\Http\Requests\Api\Admin\BlogCategory;

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
            'name'       => 'required|string|max:255',
            'slug'       => 'nullable|string|max:255|unique:blog_categories,slug',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Название категории обязательно',
            'slug.unique'   => 'Такой slug уже существует',
        ];
    }
}