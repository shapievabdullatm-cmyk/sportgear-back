<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;

class CategoryService
{
    public static function store(array $data): Category
    {
        $paramItems = $data['param_items'] ?? null;
        $paramIds   = $data['param_ids']   ?? null;
        $image      = $data['image']       ?? null;

        unset($data['param_items'], $data['param_ids'], $data['image']);

        if ($image instanceof UploadedFile) {
            $data['image'] = ImageService::uploadCategoryImage($image);
        }

        $category = Category::create($data);
        self::syncParams($category, $paramItems, $paramIds);

        return $category->fresh();
    }

    public static function update(Category $category, array $data): Category
    {
        $paramItems       = $data['param_items'] ?? null;
        $paramIds         = $data['param_ids']   ?? null;
        $image            = $data['image']       ?? null;
        $hasExplicitImage = array_key_exists('image', $data);

        unset($data['param_items'], $data['param_ids'], $data['image']);

        if ($image instanceof UploadedFile) {
            ImageService::delete($category->image);
            $data['image'] = ImageService::uploadCategoryImage($image);
        } elseif ($hasExplicitImage && is_null($image)) {
            ImageService::delete($category->image);
            $data['image'] = null;
        }

        $category->update($data);
        self::syncParams($category, $paramItems, $paramIds);

        return $category->load(['params', 'children']);
    }

    public static function reorder(array $items): void
    {
        foreach ($items as $item) {
            Category::where('id', $item['id'])->update(['position' => $item['position']]);
        }
    }

    /**
     * Собрать все параметры категории с учётом наследования от родителей.
     * Если передан $product — подставить существующие значения товара.
     *
     * Возвращает массив для JSON:
     * [
     *   {
     *     id, title, slug, filter_type, unit,
     *     options: [ { id, value, slug }, ... ],   // для SELECT/MULTISELECT/COLOR
     *     value: { param_id, value_string, ... }   // текущее значение товара или null
     *   },
     *   ...
     * ]
     */
    public static function resolveForCategoryWithValues(Category $category, ?Product $product = null): array
    {
        // Собираем цепочку категорий: текущая + все предки
        $chain   = self::buildAncestorChain($category);
        $seen    = [];
        $params  = [];

        // Идём от корня к листу — потомок может переопределить sort
        foreach (array_reverse($chain) as $cat) {
            $cat->loadMissing(['params' => fn($q) => $q->with('options')->orderByPivot('sort')]);

            foreach ($cat->params as $param) {
                if (in_array($param->id, $seen)) continue;
                $seen[]   = $param->id;
                $params[] = $param;
            }
        }

        // Текущие значения товара, индексированные по param_id
        $valuesByParam = [];
        if ($product) {
            $product->loadMissing('paramValues');
            foreach ($product->paramValues as $pv) {
                $valuesByParam[$pv->param_id][] = $pv;
            }
        }

        return array_map(function ($param) use ($valuesByParam) {
            $rows = $valuesByParam[$param->id] ?? [];

            $value = null;

            if (!empty($rows)) {
                $first = $rows[0];

                // MULTISELECT / COLOR — несколько строк с param_option_id
                if (count($rows) > 1) {
                    $value = [
                        'param_id'         => $param->id,
                        'param_option_ids' => array_values(
                            array_filter(array_column($rows, 'param_option_id'))
                        ),
                    ];
                } else {
                    $value = array_filter([
                        'param_id'         => $param->id,
                        'param_option_id'  => $first->param_option_id,
                        'param_option_ids' => null,
                        'value_string'     => $first->value_string,
                        'value_text'       => $first->value_text,
                        'value_int'        => $first->value_int,
                        'value_float'      => $first->value_float,
                        'value_boolean'    => $first->value_boolean,
                        'value_min'        => $first->value_min,
                        'value_max'        => $first->value_max,
                    ], fn($v) => !is_null($v));

                    // param_id должен быть всегда
                    $value['param_id'] = $param->id;
                }
            }

            return [
                'id'          => $param->id,
                'title'       => $param->title,
                'slug'        => $param->slug,
                'filter_type' => $param->filter_type,
                'unit'        => $param->unit ?? null,
                'options'     => $param->options->map(fn($opt) => [
                    'id'    => $opt->id,
                    'value' => $opt->value,
                    'slug'  => $opt->slug,
                ])->values()->all(),
                'value'       => $value ?: null,
            ];
        }, $params);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private static function syncParams(Category $category, ?array $paramItems, ?array $paramIds): void
    {
        if (is_array($paramItems)) {
            $sync = [];
            foreach ($paramItems as $idx => $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0) {
                    $sync[$id] = ['sort' => (int) ($item['sort'] ?? $idx)];
                }
            }
            $category->params()->sync($sync);
        } elseif (is_array($paramIds)) {
            $category->params()->sync($paramIds);
        }
    }

    /**
     * Возвращает цепочку [текущая, родитель, дед, ...] включая саму категорию.
     */
    private static function buildAncestorChain(Category $category): array
    {
        $chain = [$category];
        $current = $category;

        // Защита от бесконечного цикла (max 10 уровней)
        $i = 0;
        while ($current->parent_id && $i < 10) {
            $current = Category::find($current->parent_id);
            if (!$current) break;
            $chain[] = $current;
            $i++;
        }

        return $chain;
    }
}
