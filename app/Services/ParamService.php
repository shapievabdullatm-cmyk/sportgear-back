<?php

namespace App\Services;

use App\Enums\Param\ParamFilterTypeEnum;
use App\Models\Param;
use App\Models\ParamOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParamService
{
    // ── CRUD ─────────────────────────────────────────────────────────────────

    public static function store(array $data): Param
    {
        $data['slug'] = self::uniqueParamSlug($data['slug'] ?? $data['title']);

        return Param::create($data);
    }

    public static function update(Param $param, array $data): Param
    {
        if (isset($data['slug']) && $data['slug'] !== $param->slug) {
            $data['slug'] = self::uniqueParamSlug($data['slug'], $param->id);
        }

        $param->update($data);
        return $param->fresh();
    }

    // ── Options ───────────────────────────────────────────────────────────────

    /**
     * Синхронизация опций с сохранением ID существующих.
     * $items = [['id' => ..., 'value' => ..., 'slug' => ...], ...]
     */
    public static function syncOptions(Param $param, array $items): void
    {
        $enum = ParamFilterTypeEnum::tryFrom($param->filter_type);
        if (!$enum || !$enum->hasOptions()) {
            $param->options()->delete();
            return;
        }

        $incomingIds = collect($items)->pluck('id')->filter()->values()->all();
        $param->options()->whereNotIn('id', $incomingIds)->delete();

        $usedSlugs = [];
        foreach ($items as $index => $item) {
            $itemId = !empty($item['id']) ? (int) $item['id'] : null;

            $slug = self::uniqueOptionSlug(
                $item['slug'] ?? $item['value'],
                $param->id,
                $usedSlugs,
                $itemId
            );
            $usedSlugs[] = $slug;

            if ($itemId) {
                $param->options()->where('id', $itemId)->update([
                    'slug'  => $slug,
                    'value' => $item['value'],
                    'sort'  => $index,
                ]);
            } else {
                $param->options()->create([
                    'slug'  => $slug,
                    'value' => $item['value'],
                    'sort'  => $index,
                ]);
            }
        }
    }

    // ── Slug helpers ─────────────────────────────────────────────────────────

    public static function slugify(string $text): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];

        $text = mb_strtolower(trim($text));
        $text = strtr($text, $map);
        // Латиница, цифры и дефис
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s_-]+/', '-', $text);
        return trim($text, '-') ?: 'param';
    }

    public static function uniqueParamSlug(string $base, ?int $exceptId = null): string
    {
        $slug = self::slugify($base);
        $orig = $slug;
        $i    = 1;

        do {
            $query = DB::table('params')->where('slug', $slug);
            if ($exceptId) {
                $query->where('id', '!=', $exceptId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $orig . '-' . $i++;
        } while (true);

        return $slug;
    }

    public static function uniqueOptionSlug(string $base, int $paramId, array $alreadyUsed = [], ?int $exceptId = null): string
    {
        $slug = self::slugify($base);
        $orig = $slug;
        $i    = 1;

        while (
            in_array($slug, $alreadyUsed, true) ||
            DB::table('param_options')
                ->where('param_id', $paramId)
                ->where('slug', $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $orig . '-' . $i++;
        }

        return $slug;
    }
}
