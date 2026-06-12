<?php

namespace App\Http\Resources\QuickLink;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
