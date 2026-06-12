<?php

namespace App\Http\Resources\Address;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'full_address' => $this->full_address,
            'lat'          => $this->lat,
            'lon'          => $this->lon,
            'city'         => $this->city,
            'street'       => $this->street,
            'house'        => $this->house,
            'apartment'    => $this->apartment,
            'entrance'     => $this->entrance,
            'floor'        => $this->floor,
            'intercom'     => $this->intercom,
            'comment'      => $this->comment,
            'is_default'   => (bool) $this->is_default,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}