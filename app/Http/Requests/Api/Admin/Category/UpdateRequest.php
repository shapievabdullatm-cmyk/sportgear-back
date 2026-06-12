<?php

namespace App\Http\Requests\Api\Admin\Category;

use App\Rules\NotDescendantCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('category');

        // Если это объект модели, получаем ID
        if ($categoryId instanceof \App\Models\Category) {
            $categoryId = $categoryId->id;
        }

        return [
            // Основные поля
            'title'     => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                new NotDescendantCategory($categoryId),
            ],

            // Изображение: опционально при обновлении
            'image' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            // Признак явного удаления фото (передаётся как строка 'true' из FormData)
            'remove_image' => 'sometimes|boolean',

            // SEO
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'keywords'         => 'nullable|string|max:1000',

            // Параметры
            'param_items'          => 'sometimes|array',
            'param_items.*.id'     => 'required_with:param_items|integer|exists:params,id',
            'param_items.*.sort'   => 'required_with:param_items|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimes' => 'Разрешены только форматы: JPG, PNG, WebP.',
            'image.max'   => 'Файл не должен превышать 5 МБ.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Убедимся, что parent_id передается как integer или null
        if ($this->has('parent_id')) {
            $value = $this->input('parent_id');

            // Преобразуем пустую строку, 0, "0", null в null
            if ($value === '' || $value === 0 || $value === '0' || $value === null) {
                $this->merge(['parent_id' => null]);
            } else {
                $this->merge(['parent_id' => (int) $value]);
            }
        }
    }
}
