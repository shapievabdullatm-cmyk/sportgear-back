<?php

namespace App\Http\Requests\Api\Admin\Product;

use Illuminate\Validation\Rule;

class UpdateRequest extends StoreRequest
{
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'external_id'      => ['nullable', 'string', 'max:255', Rule::unique('products', 'external_id')->ignore($productId)],
            'title'            => 'required|string|max:255',
            'external_title'   => 'nullable|string|max:255',
            'slug'             => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'article'          => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'nullable|numeric|min:0',
            'old_price'        => 'nullable|numeric|min:0',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords'    => 'nullable|string',
            'width'            => 'nullable|numeric|min:0',
            'height'           => 'nullable|numeric|min:0',
            'length'           => 'nullable|numeric|min:0',
            'weight'           => 'nullable|numeric|min:0',
            'is_active'        => 'nullable|boolean',
            'parent_id'               => [
                'nullable',
                'exists:products,id',
                function ($attribute, $value, $fail) use ($productId) {
                    if ($value) {
                        // Нельзя установить себя как родителя
                        if ($value == $productId) {
                            $fail('Товар не может быть родителем самого себя.');
                            return;
                        }

                        // Проверяем, что родитель не имеет своего родителя
                        $parent = \App\Models\Product::find($value);
                        if ($parent && $parent->parent_id !== null) {
                            $fail('Нельзя добавить родительский товар к товару, у которого уже есть родитель.');
                        }
                    }
                },
            ],
            'category_id'             => 'sometimes|exists:categories,id',
            'product_group_id'        => 'sometimes|exists:product_groups,id',
            'brand_id'                => 'nullable|exists:brands,id',
            'brand_origin_id'         => 'nullable|exists:brand_origins,id',
            'manufacturing_country_id' => 'nullable|exists:manufacturing_countries,id',
            'size_table_id'           => 'nullable|exists:size_tables,id',

            'images'           => 'nullable|array',
            'images.*'         => 'file|image|max:10240',

            'image_order'      => 'nullable|array',
            'image_order.*'    => 'integer|min:0',

            'barcodes'         => 'nullable|array',
            'barcodes.*.barcode' => 'required_with:barcodes|string|max:50',
            'barcodes.*.type'  => 'nullable|string|max:20',

            'params'                  => 'nullable|array',
            'params.*.param_id'       => 'required_with:params|integer|exists:params,id',
            'params.*.filter_type'    => 'required_with:params|integer',
            'params.*.multiple'       => 'nullable|boolean',
            'params.*.value'          => 'nullable',
            'params.*.option_id'      => 'nullable|integer|exists:param_options,id',
            'params.*.option_ids'     => 'nullable|array',
            'params.*.option_ids.*'   => 'integer|exists:param_options,id',

            'bought_together_ids'     => 'nullable|array',
            'bought_together_ids.*'   => ['integer', 'exists:products,id', 'different:id'],

            'copy_image_ids'          => 'nullable|array',
            'copy_image_ids.*'        => 'integer|exists:product_images,id',
        ];
    }
}
