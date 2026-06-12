<?php

namespace App\Services;

use App\Enums\Param\ParamFilterTypeEnum;
use App\Models\Category;
use App\Models\Param;
use App\Models\Product;

class ParamResolver
{
    /**
     * Собрать параметры для категории с учётом наследования.
     * Порядок: сначала параметры выбранной категории, затем предки снизу вверх.
     * Если у параметра где-либо is_required=true — считаем обязательным.
     */
    public static function resolveForCategory(Category $category): array
    {
        $chain = self::getChainFromRootTo($category);

        $result = [];
        $seen   = [];

        // 1) параметры выбранной категории (последний элемент цепочки)
        foreach (self::collectCategoryParams($category) as $item) {
            $result[]        = $item;
            $seen[$item['id']] = true;
        }

        // 2) параметры предков снизу вверх (без самой выбранной)
        for ($i = count($chain) - 2; $i >= 0; $i--) {
            foreach (self::collectCategoryParams($chain[$i]) as $it) {
                if (!isset($seen[$it['id']])) {
                    $result[]          = $it;
                    $seen[$it['id']]   = true;
                } else {
                    // усиливаем required при необходимости
                    foreach ($result as &$r) {
                        if ($r['id'] === $it['id']) {
                            $r['required'] = $r['required'] || $it['required'];
                            break;
                        }
                    }
                    unset($r);
                }
            }
        }

        return $result;
    }

    /**
     * Та же структура, но с заполненными значениями продукта.
     */
    public static function resolveForCategoryWithValues(Category $category, Product $product): array
    {
        $params = self::resolveForCategory($category);
        $product->loadMissing(['paramValues', 'optionValues']);

        foreach ($params as &$p) {
            $p['value']      = null;
            $p['option_id']  = null;
            $p['option_ids'] = [];

            $pv = $product->paramValues->firstWhere('param_id', $p['id']);
            if ($pv) {
                $p['value']     = $pv->value_string
                    ?? $pv->value_text
                    ?? $pv->value_int
                    ?? $pv->value_float
                    ?? null;
                $p['option_id'] = $pv->param_option_id;
            }

            // MULTISELECT
            if (!empty($p['multiple'])) {
                $p['option_ids'] = $product->optionValues
                    ->where('param_id', $p['id'])
                    ->pluck('param_option_id')
                    ->values()
                    ->toArray();
            }
        }
        unset($p);

        return $params;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private static function collectCategoryParams(Category $category): array
    {
        $items = $category->params()
            ->orderBy('category_params.sort')
            ->with(['options' => fn($q) => $q->orderBy('sort')])
            ->get();

        return $items->map(function (Param $p) {
            $type = (int) $p->filter_type;
            $enum = ParamFilterTypeEnum::tryFrom($type);

            $multiple = $type === ParamFilterTypeEnum::MULTISELECT->value;

            return [
                'id'               => $p->id,
                'title'            => $p->title,
                'slug'             => $p->slug,
                'label'            => $p->label,
                'filter_type'      => $type,
                'filter_type_title'=> $enum?->label(),
                'has_options'      => $enum?->hasOptions() ?? false,
                'unit'             => $p->unit,
                'is_filterable'    => (bool) $p->is_filterable,
                'is_searchable'    => (bool) $p->is_searchable,
                'is_comparable'    => (bool) $p->is_comparable,
                'is_size'          => (bool) $p->is_size,
                'required'         => (bool) $p->pivot->is_required,
                'multiple'         => $multiple,
                'options'          => $p->options->map(fn($opt) => [
                    'id'    => $opt->id,
                    'slug'  => $opt->slug,
                    'value' => $opt->value,
                ])->toArray(),
            ];
        })->toArray();
    }

    private static function getChainFromRootTo(Category $category): array
    {
        $chain = [];
        $cur   = $category;
        while ($cur) {
            $chain[] = $cur;
            $cur     = $cur->parent;
        }
        return array_reverse($chain);
    }
}
