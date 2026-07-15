<?php

namespace App\Http\Requests\Account;

use App\Support\Geography\InvalidUbigeoException;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCustomerAddressRequest extends FormRequest
{
    protected $errorBag = 'address';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $provinceCodes = array_column(
            app(LimaCallaoUbigeoCatalog::class)->provinces(),
            'code'
        );

        return [
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^9\d{8}$/'],
            'province_code' => ['required', 'string', Rule::in($provinceCodes)],
            'district_code' => ['required', 'string', 'size:6'],
            'address_line' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Ingresa una etiqueta para identificar la direccion.',
            'label.max' => 'La etiqueta no debe superar los 50 caracteres.',
            'recipient_name.required' => 'Ingresa el nombre de la persona que recibira el pedido.',
            'recipient_name.max' => 'El destinatario no debe superar los 120 caracteres.',
            'phone.required' => 'Ingresa un celular de contacto.',
            'phone.regex' => 'Ingresa un celular peruano valido de 9 digitos.',
            'province_code.required' => 'Selecciona una provincia.',
            'province_code.in' => 'La provincia seleccionada no pertenece al area de entrega.',
            'district_code.required' => 'Selecciona un distrito.',
            'district_code.size' => 'El distrito seleccionado no es valido.',
            'address_line.required' => 'Ingresa la direccion de entrega.',
            'address_line.max' => 'La direccion no debe superar los 255 caracteres.',
            'reference.max' => 'La referencia no debe superar los 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone'));
        $reference = Str::squish((string) $this->input('reference'));

        $this->merge([
            'label' => Str::squish((string) $this->input('label')),
            'recipient_name' => Str::squish((string) $this->input('recipient_name')),
            'phone' => $phone,
            'province_code' => trim((string) $this->input('province_code')),
            'district_code' => trim((string) $this->input('district_code')),
            'address_line' => Str::squish((string) $this->input('address_line')),
            'reference' => $reference !== '' ? $reference : null,
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->has('province_code')
                || $validator->errors()->has('district_code')
            ) {
                return;
            }

            try {
                app(LimaCallaoUbigeoCatalog::class)->resolve(
                    $this->string('province_code')->toString(),
                    $this->string('district_code')->toString()
                );
            } catch (InvalidUbigeoException) {
                $validator->errors()->add(
                    'district_code',
                    'El distrito no pertenece a la provincia seleccionada.'
                );
            }
        });
    }
}
