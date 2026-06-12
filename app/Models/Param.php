<?php

namespace App\Models;

use App\Enums\Param\ParamFilterTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Param extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'label',
        'filter_type',
        'unit',
        'is_filterable',
        'is_searchable',
        'is_comparable',
        'is_size',
        'sort',
    ];

    protected $casts = [
        'filter_type'   => 'integer',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean',
        'is_comparable' => 'boolean',
        'is_size'       => 'boolean',
        'sort'          => 'integer',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_params')
            ->withPivot(['sort', 'is_required']);
    }

    public function options()
    {
        return $this->hasMany(ParamOption::class)->orderBy('sort');
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getFilterTypeTitleAttribute(): ?string
    {
        $enum = ParamFilterTypeEnum::tryFrom($this->filter_type);
        return $enum?->label();
    }

    public function getFilterTypeEnumAttribute(): ?ParamFilterTypeEnum
    {
        return ParamFilterTypeEnum::tryFrom($this->filter_type);
    }

    public function getHasOptionsAttribute(): bool
    {
        return $this->filterTypeEnum?->hasOptions() ?? false;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeFilterable(Builder $query): Builder
    {
        return $query->where('is_filterable', true);
    }

    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('is_searchable', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('title');
    }

    // ── Route model binding ──────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'id'; // используем id в API; slug — для SEO-роутов фронта
    }
}
