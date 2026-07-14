<?php

namespace App\Http\Requests\Cart;

use App\Models\Product;
use App\Support\Cart\CartService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class UpdateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Ingresa la cantidad que deseas comprar.',
            'quantity.integer' => 'La cantidad debe ser un numero entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'quantity' => 'cantidad',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => $this->input('quantity') !== null ? (int) $this->input('quantity') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');

            if (! $product instanceof Product) {
                return;
            }

            $isVisible = Product::query()
                ->active()
                ->whereKey($product->id)
                ->exists();

            if (! $isVisible) {
                $validator->errors()->add('product', 'Este producto no esta disponible en la tienda.');
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
