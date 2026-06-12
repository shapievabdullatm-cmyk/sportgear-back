<?php

namespace App\Http\Resources\Blog;

use App\Http\Resources\Blog\BlogCategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'category'     => $this->whenLoaded('category', fn () => $this->category ? BlogCategoryResource::make($this->category) : null),
            'title'        => $this->title,
            'slug'         => $this->slug,
            'banner_image' => $this->banner_image ? \App\Services\ImageService::url($this->banner_image) : null,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}