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
            'use_default_delivery_window' => ['required', 'boolean'],
            'delivery_business_days_min' => ['nullable', 'required_if:use_default_delivery_window,0', 'integer', 'min:1', 'max:30'],
            'delivery_business_days_max' => ['nullable', 'required_if:use_default_delivery_window,0', 'integer', 'min:1', 'max:30', 'gte:delivery_business_days_min'],
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
            'delivery_business_days_min.required_if' => 'Ingresa el plazo minimo del distrito.',
            'delivery_business_days_min.min' => 'El plazo minimo debe ser al menos 1 dia de atencion.',
            'delivery_business_days_max.required_if' => 'Ingresa el plazo maximo del distrito.',
            'delivery_business_days_max.gte' => 'El plazo maximo no puede ser menor que el minimo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'use_default_delivery_window' => $this->boolean('use_default_delivery_window'),
            'delivery_business_days_min' => $this->filled('delivery_business_days_min')
                ? $this->input('delivery_business_days_min')
                : null,
            'delivery_business_days_max' => $this->filled('delivery_business_days_max')
                ? $this->input('delivery_business_days_max')
                : null,
        ]);
    }
}
