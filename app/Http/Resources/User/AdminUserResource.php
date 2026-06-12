<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Address\AddressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'phone'         => $this->phone,
            'email'         => $this->email,
            'pending_email' => $this->pending_email,
            'gender'        => $this->gender,
            'birth_date'    => $this->birth_date?->format('Y-m-d'),
            'addresses'     => AddressResource::collection($this->whenLoaded('addresses')),
            'roles'         => $this->whenLoaded('roles', fn() => $this->roles->pluck('title')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}