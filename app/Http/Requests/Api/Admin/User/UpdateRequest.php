<?php

namespace App\Http\Requests\Api\Admin\User;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNormalizer::normalize($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'first_name' => 'nullable|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'phone'      => ['required', 'string', 'regex:/^\+7\d{10}$/', Rule::unique('users', 'phone')->ignore($userId)],
            'email'      => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'gender'     => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date|before:today',

            'addresses'                  => 'nullable|array',
            'addresses.*.id'             => 'nullable|integer|exists:addresses,id',
            'addresses.*.title'          => 'required|string|max:100',
            'addresses.*.full_address'   => 'required|string|max:500',
            'addresses.*.lat'            => 'nullable|numeric',
            'addresses.*.lon'            => 'nullable|numeric',
            'addresses.*.city'           => 'nullable|string|max:100',
            'addresses.*.street'         => 'nullable|string|max:200',
            'addresses.*.house'          => 'required|string|max:20',
            'addresses.*.apartment'      => 'nullable|string|max:20',
            'addresses.*.entrance'       => 'nullable|string|max:10',
            'addresses.*.floor'          => 'nullable|string|max:10',
            'addresses.*.intercom'       => 'nullable|string|max:20',
            'addresses.*.comment'        => 'nullable|string|max:500',
            'addresses.*.is_default'     => 'nullable|boolean',
        ];
    }
}