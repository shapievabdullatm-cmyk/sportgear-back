<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;
    protected $fillable = [
        'external_id',
        'title',
        'external_title',
        'slug',
        'article',
        'description',
        'price',
        'old_price',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'weight',
        'length',
        'width',
        'height',
        'is_active',
        'parent_id',
        'category_id',
        'product_group_id',
        'brand_id',
        'brand_origin_id',
        'manufacturing_country_id',
        'size_table_id',
    ];

    protected $casts = [
        'price'     => 'float',
        'old_price' => 'float',
        'width'     => 'float',
        'height'    => 'float',
        'length'    => 'float',
        'weight'    => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($product) {
            // ✅ Генерим slug только если есть title
            if (empty($product->slug) && !empty($product->title)) {
                $product->slug = self::generateUniqueSlug($product->title);
            }
        });

        static::updating(function ($product) {
            // ✅ Если title изменился — обновляем slug (если он пустой)
            if (
                $product->isDirty('title') &&
                !empty($product->title) &&
                empty($product->slug)
            ) {
                $product->slug = self::generateUniqueSlug($product->title);
            }
        });

        static::deleting(function ($product) {
            // ✅ Удаляем все связанные изображения с файлами
            foreach ($product->images as $image) {
                \App\Services\ProductImageService::destroy($image);
            }

            // ✅ Удаляем папку продукта из S3, если она пустая
            $productFolder = 'products/' . $product->id;
            if (\Storage::disk('s3')->exists($productFolder)) {
                $files = \Storage::disk('s3')->files($productFolder);
                if (empty($files)) {
                    \Storage::disk('s3')->deleteDirectory($productFolder);
                }
            }

            // ✅ Удаляем значения параметров
            $product->paramValues()->delete();
            $product->optionValues()->delete();
        });
    }

    public static function generateUniqueSlug(?string $title): string
    {
        // ✅ Защита от null
        if (empty($title)) {
            return 'product-' . uniqid();
        }

        $slug = Str::slug($title);

        // если вдруг slug не сгенерился (например, кириллица без транслита)
        if (empty($slug)) {
            $slug = 'product';
        }

        $originalSlug = $slug;
        $i = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $i++;
        }

        return $slug;
    }

    /** Изображения отсортированы по sort_order */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function paramValues(): HasMany
    {
        return $this->hasMany(ProductParamValue::class);
    }

    public function optionValues(): HasMany
    {
        return $this->hasMany(ProductParamOptionValue::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function brandOrigin(): BelongsTo
    {
        return $this->belongsTo(BrandOrigin::class);
    }

    public function manufacturingCountry(): BelongsTo
    {
        return $this->belongsTo(ManufacturingCountry::class);
    }

    public function sizeTable(): BelongsTo
    {
        return $this->belongsTo(SizeTable::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    // ── Scout / Meilisearch ───────────────────────────────────────────────────

    /** Only root active products are indexed. */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && is_null($this->parent_id);
    }

    /** Eager-load relations when bulk-importing via scout:import. */
    public function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with([
            'barcodes',
            'category',
            'paramValues.param',
            'paramValues.paramOption',
            'optionValues.param',
            'optionValues.paramOption',
        ]);
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing([
            'barcodes',
            'category',
            'paramValues.param',
            'paramValues.paramOption',
            'optionValues.param',
            'optionValues.paramOption',
        ]);

        $paramValues = $this->buildSearchableParamValues();

        return [
            'id'             => $this->id,
            'title'          => (string) ($this->title ?? ''),
            'external_title' => (string) ($this->external_title ?? ''),
            'article'        => (string) ($this->article ?? ''),
            'slug'           => $this->slug,
            'price'          => (float) $this->price,
            'old_price'      => $this->old_price ? (float) $this->old_price : null,
            'is_active'      => (bool) $this->is_active,
            'parent_id'      => $this->parent_id,
            'category_id'    => $this->category_id,
            'category_slug'  => $this->category?->slug,
            'barcodes'       => $this->barcodes->pluck('barcode')->all(),
            'meta_keywords'  => (string) ($this->meta_keywords ?? ''),
            'param_values'   => $paramValues,
        ];
    }

    private function buildSearchableParamValues(): array
    {
        $values = collect();

        foreach ($this->paramValues as $pv) {
            if (! $pv->param?->is_searchable) {
                continue;
            }
            if ($pv->param_option_id && $pv->paramOption) {
                $values->push($pv->paramOption->value);
            } elseif ($pv->value_string) {
                $values->push($pv->value_string);
            }
        }

        foreach ($this->optionValues as $ov) {
            if ($ov->param?->is_searchable && $ov->paramOption) {
                $values->push($ov->paramOption->value);
            }
        }

        return $values->unique()->values()->all();
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function boughtTogetherProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related_products',
            'product_id',
            'related_product_id'
        )
        ->withTimestamps()
        ->withPivot('sort')
        ->orderBy('sort');
    }

    /**
     * Получить общее количество товара на всех складах.
     * Для родительского товара с вариантами учитываются и остатки детей —
     * иначе родитель скрывался бы как «нет в наличии», когда реальные остатки
     * лежат на дочерних вариантах (размеры).
     */
    public function getTotalStockAttribute(): int
    {
        $own = (int) $this->stocks()->sum('quantity');

        if (is_null($this->parent_id)) {
            $own += (int) ProductStock::whereIn(
                'product_id',
                Product::where('parent_id', $this->id)
                    ->where('is_active', true)
                    ->select('id')
            )->sum('quantity');
        }

        return $own;
    }

    /**
     * Получить доступное количество товара на всех складах.
     * Для родительского товара суммируется и доступное количество детей.
     */
    public function getAvailableStockAttribute(): int
    {
        $own = $this->stocks()->get()->sum(fn($stock) => $stock->available_quantity);

        if (is_null($this->parent_id)) {
            $childStocks = ProductStock::whereIn(
                'product_id',
                Product::where('parent_id', $this->id)
                    ->where('is_active', true)
                    ->select('id')
            )->get();

            $own += $childStocks->sum(fn($stock) => $stock->available_quantity);
        }

        return (int) $own;
    }
}
