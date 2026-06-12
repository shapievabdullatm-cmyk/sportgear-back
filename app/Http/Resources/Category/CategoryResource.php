<?php

namespace App\Http\Resources\Category;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'slug'             => $this->slug,

            // Возвращаем полный URL изображения (или null)
            'image'            => $this->image ? ImageService::url($this->image) : null,

            // SEO
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'keywords'         => $this->keywords,

            // Иерархия и порядок
            'parent_id'        => $this->parent_id,
            'position'         => $this->position,

            // Параметры
            'param_ids'   => $this->resource->params()->pluck('id')->toArray(),
            'param_items' => $this->resource->params()
                ->orderBy('category_params.sort')
                ->get()
                ->map(fn ($p) => [
                    'id'   => $p->id,
                    'sort' => (int) $p->pivot->sort,
                ])->toArray(),

            // Листовая ли категория (нет дочерних) — используется во фронте
            'is_leaf' => !$this->resource->children()->exists(),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
