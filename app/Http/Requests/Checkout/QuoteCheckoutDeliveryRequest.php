<?php

namespace App\Http\Requests\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\CustomerAddress;
use App\Support\Geography\InvalidUbigeoException;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuoteCheckoutDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists(CustomerAddress::class, 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->user()?->getKey())),
            ],
            'ubigeo' => ['nullable', 'string', 'size:6'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'delivery_method.required' => 'Selecciona una modalidad para cotizar.',
            'delivery_method.enum' => 'La modalidad de entrega no es valida.',
            'address_id.exists' => 'La direccion seleccionada no pertenece a tu cuenta o ya no existe.',
            'ubigeo.size' => 'El distrito seleccionado no es valido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_method' => trim((string) $this->input('delivery_method')),
            'address_id' => $this->filled('address_id') ? (int) $this->input('address_id') : null,
            'ubigeo' => $this->filled('ubigeo') ? trim((string) $this->input('ubigeo')) : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $method = $this->input('delivery_method');
            $hasAddress = $this->input('address_id') !== null;
            $hasUbigeo = is_string($this->input('ubigeo')) && $this->input('ubigeo') !== '';

            if ($method === DeliveryMethod::Pickup->value) {
                if ($hasAddress || $hasUbigeo) {
                    $validator->errors()->add(
                        'delivery_method',
                        'El recojo no admite una direccion de entrega.',
                    );
                }

                return;
            }

            if ($method !== DeliveryMethod::HomeDelivery->value) {
                return;
            }

            if ($hasAddress === $hasUbigeo) {
                $validator->errors()->add(
                    'address_id',
                    'Selecciona una direccion guardada o el distrito de la nueva direccion.',
                );

                return;
            }

            if (! $hasUbigeo || $validator->errors()->has('ubigeo')) {
                return;
            }

            try {
                app(LimaCallaoUbigeoCatalog::class)->resolve(
                    substr((string) $this->input('ubigeo'), 0, 4),
                    (string) $this->input('ubigeo'),
                );
            } catch (InvalidUbigeoException) {
                $validator->errors()->add('ubigeo', 'El distrito seleccionado no es valido.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Revisa la modalidad y la direccion seleccionadas.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
