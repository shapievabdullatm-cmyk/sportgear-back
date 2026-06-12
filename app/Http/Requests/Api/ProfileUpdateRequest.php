<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'gender'     => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date|before:today',
        ];
    }
}