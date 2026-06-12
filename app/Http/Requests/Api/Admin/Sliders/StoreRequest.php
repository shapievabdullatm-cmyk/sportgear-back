<?php

namespace App\Http\Requests\Api\Admin\Sliders;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
        return [
            'title'        => 'required|string|max:255',
            'link'         => 'required|url|max:500',
            'image'        => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mobile_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
