<?php

namespace App\Http\Resources\Category;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PopularCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cat = $this->category;

        return [
            'id'          => $this->id,           // popular_categories.id
            'position'    => $this->position,
            'category_id' => $cat->id,
            'title'       => $cat->title,
            'slug'        => $cat->slug,
            'image'       => $cat->image ? ImageService::url($cat->image) : null,
        ];
    }
}
