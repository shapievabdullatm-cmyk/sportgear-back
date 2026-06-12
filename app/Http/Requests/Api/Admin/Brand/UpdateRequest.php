<?php

namespace App\Http\Requests\Api\Admin\Brand;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $brandId = $this->route('brand')?->id ?? $this->route('brand');

        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:brands,slug,' . $brandId,
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'remove_image' => 'nullable|boolean',
        ];
    }
}