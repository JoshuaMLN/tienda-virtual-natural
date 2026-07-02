<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:brands,slug'],
            'logo' => ['nullable', 'image', 'max:3072'],
            'cropped_logo' => ['nullable', 'string'],
            'remove_logo' => ['sometimes', 'boolean'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => Brand::uniqueSlug($slug !== '' ? $slug : $name),
            'is_active' => $this->boolean('is_active'),
            'remove_logo' => $this->boolean('remove_logo'),
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
    }
}
