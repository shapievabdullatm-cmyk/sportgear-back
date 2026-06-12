<?php

namespace App\Http\Requests\Api\Admin\BlogCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('blog_category')->id;

        return [
            'name'       => 'required|string|max:255',
            'slug'       => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('blog_categories', 'slug')->ignore($categoryId),
            ],
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