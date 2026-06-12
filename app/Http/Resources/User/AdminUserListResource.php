<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'gender'          => $this->gender,
            'addresses_count' => $this->whenCounted('addresses'),
            'created_at'      => $this->created_at,
        ];
    }
}