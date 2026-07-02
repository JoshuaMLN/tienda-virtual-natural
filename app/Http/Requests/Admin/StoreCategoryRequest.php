<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Support\Catalog\CategoryIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:3072'],
            'cropped_image' => ['nullable', 'string'],
            'remove_image' => ['sometimes', 'boolean'],
            'icon_class' => ['required', 'string', Rule::in(CategoryIcon::values())],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => Category::uniqueSlug($slug !== '' ? $slug : $name),
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'remove_image' => $this->boolean('remove_image'),
            'icon_class' => $this->input('icon_class') ?: CategoryIcon::default(),
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
    }
}
