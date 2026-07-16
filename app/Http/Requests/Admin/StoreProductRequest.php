<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaxAffectation;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'usage_instructions' => ['nullable', 'string'],
            'main_image' => ['nullable', 'image', 'max:4096'],
            'cropped_main_image' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'tax_affectation' => ['required', Rule::enum(TaxAffectation::class)],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => Product::uniqueSlug($slug !== '' ? $slug : $name),
            'sku' => trim((string) $this->input('sku')),
            'brand_id' => $this->input('brand_id') ?: null,
            'compare_at_price' => $this->input('compare_at_price') !== '' ? $this->input('compare_at_price') : null,
            'tax_affectation' => $this->input('tax_affectation', TaxAffectation::Taxed->value),
            'stock' => $this->input('stock') ?? 0,
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'published_at' => $this->input('published_at') ?: null,
        ]);
    }
}
