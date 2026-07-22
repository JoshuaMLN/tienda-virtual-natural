<?php

namespace App\Http\Requests\Account;

use App\Enums\CustomerOrderFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9\-]+$/'],
            'estado' => ['nullable', Rule::enum(CustomerOrderFilter::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.max' => 'El codigo de pedido no debe superar los 32 caracteres.',
            'q.regex' => 'Ingresa un codigo de pedido valido.',
            'estado.enum' => 'El filtro de estado seleccionado no es valido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = strtoupper(trim((string) $this->input('q', '')));

        $this->merge([
            'q' => $search !== '' ? $search : null,
            'estado' => $this->input('estado') ?: CustomerOrderFilter::All->value,
        ]);
    }
}
