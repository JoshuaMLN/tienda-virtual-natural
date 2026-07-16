<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryDistrictRequest extends FormRequest
{
    protected $errorBag = 'deliveryDistrict';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '_delivery_district_id' => ['required', 'integer'],
            'shipping_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_fee.required' => 'Ingresa la tarifa de envio.',
            'shipping_fee.numeric' => 'La tarifa debe ser numerica.',
            'shipping_fee.decimal' => 'La tarifa admite hasta 2 decimales.',
            'shipping_fee.min' => 'La tarifa no puede ser negativa.',
            'shipping_fee.max' => 'La tarifa no puede superar S/ 999.99.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
