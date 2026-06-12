<?php

namespace App\Http\Requests\Api\Admin\Param;

use App\Enums\Param\ParamFilterTypeEnum;
use App\Services\ParamService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paramId = $this->route('param')?->id;

        return [
            'title'            => 'required|string|max:255',
            'slug'             => "nullable|string|max:255|regex:/^[a-z0-9-]+$/|unique:params,slug,{$paramId}",
            'filter_type'      => 'required|integer|in:' . ParamFilterTypeEnum::valuesAsString(),
            'unit'             => 'nullable|string|max:32',
            'is_filterable'    => 'boolean',
            'is_searchable'    => 'boolean',
            'is_comparable'    => 'boolean',
            'sort'             => 'integer|min:0',

            'options'          => 'nullable|array',
            'options.*.value'  => 'required|string|max:500',
            'options.*.slug'   => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex'  => 'Слаг может содержать только строчные латинские буквы, цифры и дефисы.',
            'slug.unique' => 'Такой слаг уже занят.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->slug) && $this->title) {
            $this->merge(['slug' => ParamService::slugify($this->title)]);
        }
    }
}
