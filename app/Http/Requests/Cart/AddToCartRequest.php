<?php

namespace App\Http\Requests\Cart;

use App\Models\Product;
use App\Support\Cart\CartService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Selecciona un producto para agregar al carrito.',
            'product_id.integer' => 'Selecciona un producto valido.',
            'product_id.exists' => 'El producto seleccionado ya no existe.',
            'quantity.required' => 'Ingresa la cantidad que deseas agregar.',
            'quantity.integer' => 'La cantidad debe ser un numero entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'quantity' => 'cantidad',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_id' => $this->input('product_id') !== null ? (int) $this->input('product_id') : null,
            'quantity' => $this->input('quantity') !== null ? (int) $this->input('quantity') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('product_id')) {
                return;
            }

            $isVisible = Product::query()
                ->active()
                ->whereKey($this->integer('product_id'))
                ->exists();

            if (! $isVisible) {
                $validator->errors()->add('product_id', 'Este producto no esta disponible en la tienda.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Revisa los datos del carrito.',
            'cart' => app(CartService::class)->get()->toArray(),
            'warnings' => [],
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
