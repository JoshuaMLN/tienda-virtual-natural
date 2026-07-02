<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($brand?->id)],
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
        $brand = $this->route('brand');
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => Brand::uniqueSlug($slug !== '' ? $slug : $name, $brand?->id),
            'is_active' => $this->boolean('is_active'),
            'remove_logo' => $this->boolean('remove_logo'),
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
    }
}
