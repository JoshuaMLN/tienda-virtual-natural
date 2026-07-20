<?php

namespace App\Http\Requests\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\CustomerAddress;
use App\Support\Addresses\CustomerAddressService;
use App\Support\Delivery\DeliveryService;
use App\Support\Geography\InvalidUbigeoException;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCheckoutContactAddressRequest extends FormRequest
{
    protected $errorBag = 'checkout';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $provinceCodes = array_column(
            app(LimaCallaoUbigeoCatalog::class)->provinces(),
            'code',
        );

        return [
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'regex:/^9\d{8}$/'],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'quote_reference' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'address_choice' => [
                'exclude_unless:delivery_method,'.DeliveryMethod::HomeDelivery->value,
                'required',
                'string',
                'regex:/^(?:new|address:\d+)$/',
            ],
            'address_mode' => [
                'exclude_unless:delivery_method,'.DeliveryMethod::HomeDelivery->value,
                'required',
                Rule::in(['existing', 'new']),
            ],
            'address_id' => [
                'exclude_unless:address_mode,existing',
                'required',
                'integer',
                Rule::exists(CustomerAddress::class, 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->user()?->getKey())),
            ],
            'label' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:50'],
            'recipient_name' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:120'],
            'phone' => ['exclude_unless:address_mode,new', 'required', 'string', 'regex:/^9\d{8}$/'],
            'province_code' => ['exclude_unless:address_mode,new', 'required', 'string', Rule::in($provinceCodes)],
            'district_code' => ['exclude_unless:address_mode,new', 'required', 'string', 'size:6'],
            'address_line' => ['exclude_unless:address_mode,new', 'required', 'string', 'max:255'],
            'reference' => ['exclude_unless:address_mode,new', 'nullable', 'string', 'max:255'],
            'is_default' => ['exclude_unless:address_mode,new', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_name.required' => 'Ingresa el nombre de contacto para esta compra.',
            'contact_name.max' => 'El nombre de contacto no debe superar los 120 caracteres.',
            'contact_phone.required' => 'Ingresa un celular de contacto para esta compra.',
            'contact_phone.regex' => 'Ingresa un celular peruano valido de 9 digitos.',
            'delivery_method.required' => 'Selecciona entrega a domicilio o recojo en tienda.',
            'delivery_method.enum' => 'La modalidad de entrega seleccionada no es valida.',
            'quote_reference.required' => 'Calcula y revisa la cotizacion antes de guardar estos datos.',
            'quote_reference.regex' => 'La cotizacion mostrada ya no es valida. Vuelve a calcularla.',
            'address_choice.required' => 'Selecciona una direccion o agrega una nueva.',
            'address_choice.regex' => 'La seleccion de direccion no es valida.',
            'address_id.required' => 'Selecciona una direccion guardada.',
            'address_id.exists' => 'La direccion seleccionada no pertenece a tu cuenta o ya no existe.',
            'label.required' => 'Ingresa una etiqueta para identificar la direccion.',
            'label.max' => 'La etiqueta no debe superar los 50 caracteres.',
            'recipient_name.required' => 'Ingresa el nombre de la persona que recibira el pedido.',
            'recipient_name.max' => 'El destinatario no debe superar los 120 caracteres.',
            'phone.required' => 'Ingresa un celular para la entrega.',
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
        $deliveryMethod = trim((string) $this->input('delivery_method'));
        $choice = trim((string) $this->input('address_choice'));
        $isHomeDelivery = $deliveryMethod === DeliveryMethod::HomeDelivery->value;
        $isExisting = $isHomeDelivery && str_starts_with($choice, 'address:');
        $reference = Str::squish((string) $this->input('reference'));

        $this->merge([
            'contact_name' => Str::squish((string) $this->input('contact_name')),
            'contact_phone' => $this->digits($this->input('contact_phone')),
            'delivery_method' => $deliveryMethod,
            'quote_reference' => strtolower(trim((string) $this->input('quote_reference'))),
            'address_choice' => $isHomeDelivery ? $choice : null,
            'address_mode' => $isHomeDelivery ? ($isExisting ? 'existing' : 'new') : null,
            'address_id' => $isExisting ? (int) Str::after($choice, 'address:') : null,
            'label' => Str::squish((string) $this->input('label')),
            'recipient_name' => Str::squish((string) $this->input('recipient_name')),
            'phone' => $this->digits($this->input('phone')),
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
            if ($this->input('delivery_method') === DeliveryMethod::Pickup->value) {
                if (! app(DeliveryService::class)->pickupAvailable()) {
                    $validator->errors()->add(
                        'delivery_method',
                        'El recojo en tienda no esta disponible en este momento.',
                    );
                }

                return;
            }

            if ($this->input('delivery_method') !== DeliveryMethod::HomeDelivery->value) {
                return;
            }

            if ($this->input('address_mode') === 'existing') {
                if ($validator->errors()->has('address_id')) {
                    return;
                }

                $address = $this->user()?->addresses()
                    ->whereKey((int) $this->input('address_id'))
                    ->first();

                if ($address !== null && ! app(DeliveryService::class)->hasCoverage($address->ubigeo)) {
                    $validator->errors()->add(
                        'address_choice',
                        'La entrega a domicilio no esta disponible para el distrito seleccionado.',
                    );
                }

                return;
            }

            if ($this->input('address_mode') !== 'new') {
                return;
            }

            if ($this->user()?->addresses()->count() >= CustomerAddressService::MAX_ADDRESSES) {
                $validator->errors()->add(
                    'address_choice',
                    'Alcanzaste el limite de 10 direcciones. Selecciona una direccion guardada.',
                );

                return;
            }

            if (
                $validator->errors()->has('province_code')
                || $validator->errors()->has('district_code')
            ) {
                return;
            }

            try {
                app(LimaCallaoUbigeoCatalog::class)->resolve(
                    $this->string('province_code')->toString(),
                    $this->string('district_code')->toString(),
                );
            } catch (InvalidUbigeoException) {
                $validator->errors()->add(
                    'district_code',
                    'El distrito no pertenece a la provincia seleccionada.',
                );

                return;
            }

            if (! app(DeliveryService::class)->hasCoverage($this->string('district_code')->toString())) {
                $validator->errors()->add(
                    'district_code',
                    'La entrega a domicilio no esta disponible para este distrito.',
                );
            }
        });
    }

    private function digits(mixed $value): string
    {
        return (string) preg_replace('/\D+/', '', (string) $value);
    }
}
