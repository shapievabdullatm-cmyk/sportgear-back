<?php

namespace App\Http\Resources\Param;

use App\Enums\Param\ParamFilterTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enum = ParamFilterTypeEnum::tryFrom((int) $this->filter_type);

        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'slug'             => $this->slug,
            'label'            => $this->label,
            'filter_type'      => (int) $this->filter_type,
            'filter_type_title'=> $enum?->label(),
            'has_options'      => $enum?->hasOptions() ?? false,
            'supports_unit'    => true, // единица измерения доступна для всех типов
            'unit'             => $this->unit,
            'is_filterable'    => (bool) $this->is_filterable,
            'is_searchable'    => (bool) $this->is_searchable,
            'is_comparable'    => (bool) $this->is_comparable,
            'sort'             => (int) $this->sort,

            // Опции только если загружены (with('options'))
            'options' => $this->when(
                $this->relationLoaded('options'),
                fn() => $this->options->map(fn($opt) => [
                    'id'    => $opt->id,
                    'slug'  => $opt->slug,
                    'value' => $opt->value,
                    'sort'  => $opt->sort,
                ]),
                []
            ),
        ];
    }
}
