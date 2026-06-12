<?php

namespace App\Http\Resources\ManufacturingCountry;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturingCountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'slug'     => $this->slug,
            'flag_url' => $this->flag_url ? url('storage/' . $this->flag_url) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
