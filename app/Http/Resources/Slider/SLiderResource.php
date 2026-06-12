<?php

namespace App\Http\Resources\Slider;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SLiderResource extends JsonResource
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
            'title'        => $this->title,
            'link'         => $this->link,
            'image'        => $this->image ? ImageService::url($this->image) : null,
            'mobile_image' => $this->mobile_image ? ImageService::url($this->mobile_image) : null,
            'position'     => $this->position,
        ];
    }
}
