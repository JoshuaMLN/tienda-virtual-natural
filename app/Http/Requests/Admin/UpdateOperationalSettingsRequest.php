<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateOperationalSettingsRequest extends FormRequest
{
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
            'contact_whatsapp' => ['required', 'string', 'regex:/^9\d{8}$/'],
            'contact_email' => ['required', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'business_hours_weekdays_open' => ['required', 'date_format:H:i'],
            'business_hours_weekdays_close' => ['required', 'date_format:H:i', 'after:business_hours_weekdays_open'],
            'business_hours_saturday_open' => ['nullable', 'date_format:H:i', 'required_with:business_hours_saturday_close'],
            'business_hours_saturday_close' => ['nullable', 'date_format:H:i', 'required_with:business_hours_saturday_open', 'after:business_hours_saturday_open'],
            'business_hours_sunday_open' => ['nullable', 'date_format:H:i', 'required_with:business_hours_sunday_close'],
            'business_hours_sunday_close' => ['nullable', 'date_format:H:i', 'required_with:business_hours_sunday_open', 'after:business_hours_sunday_open'],
            'free_shipping_threshold' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999.99'],
            'stock_reservation_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'delivery_business_days_min' => ['required', 'integer', 'min:1', 'max:30'],
            'delivery_business_days_max' => ['required', 'integer', 'min:1', 'max:30', 'gte:delivery_business_days_min'],
            'pickup_preparation_business_days_min' => ['required', 'integer', 'min:1', 'max:30'],
            'pickup_preparation_business_days_max' => ['required', 'integer', 'min:1', 'max:30', 'gte:pickup_preparation_business_days_min'],
            'pickup_address' => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_whatsapp.required' => 'Ingresa el WhatsApp de atencion.',
            'contact_whatsapp.regex' => 'Ingresa un celular peruano valido de 9 digitos.',
            'contact_email.required' => 'Ingresa el correo de contacto.',
            'contact_email.email' => 'Ingresa un correo de contacto valido.',
            'business_hours_weekdays_open.required' => 'Ingresa la hora de apertura de lunes a viernes.',
            'business_hours_weekdays_close.required' => 'Ingresa la hora de cierre de lunes a viernes.',
            'business_hours_weekdays_close.after' => 'La hora de cierre debe ser posterior a la apertura.',
            'business_hours_saturday_open.required_with' => 'Completa la apertura del sabado o deja ambas horas vacias.',
            'business_hours_saturday_close.required_with' => 'Completa el cierre del sabado o deja ambas horas vacias.',
            'business_hours_saturday_close.after' => 'El cierre del sabado debe ser posterior a la apertura.',
            'business_hours_sunday_open.required_with' => 'Completa la apertura del domingo o deja ambas horas vacias.',
            'business_hours_sunday_close.required_with' => 'Completa el cierre del domingo o deja ambas horas vacias.',
            'business_hours_sunday_close.after' => 'El cierre del domingo debe ser posterior a la apertura.',
            'free_shipping_threshold.required' => 'Ingresa el umbral de envio gratis.',
            'free_shipping_threshold.numeric' => 'El umbral de envio gratis debe ser numerico.',
            'free_shipping_threshold.decimal' => 'El umbral admite hasta 2 decimales.',
            'free_shipping_threshold.min' => 'El umbral no puede ser negativo.',
            'stock_reservation_minutes.required' => 'Ingresa el tiempo disponible para completar el pago.',
            'stock_reservation_minutes.min' => 'El tiempo para completar el pago debe ser de al menos 5 minutos.',
            'stock_reservation_minutes.max' => 'El tiempo para completar el pago no puede superar 24 horas.',
            'delivery_business_days_min.required' => 'Ingresa el plazo minimo de entrega.',
            'delivery_business_days_min.min' => 'El plazo minimo debe ser al menos 1 dia de atencion.',
            'delivery_business_days_max.required' => 'Ingresa el plazo maximo de entrega.',
            'delivery_business_days_max.gte' => 'El plazo maximo no puede ser menor que el minimo.',
            'pickup_preparation_business_days_min.required' => 'Ingresa el plazo minimo para preparar un recojo.',
            'pickup_preparation_business_days_max.required' => 'Ingresa el plazo maximo para preparar un recojo.',
            'pickup_preparation_business_days_max.gte' => 'La preparacion maxima no puede ser menor que la minima.',
            'pickup_address.min' => 'Ingresa una direccion de recojo completa.',
            'pickup_address.max' => 'La direccion de recojo no debe superar los 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $this->input('contact_whatsapp')) ?? '';

        if (strlen($whatsapp) === 11 && str_starts_with($whatsapp, '51')) {
            $whatsapp = substr($whatsapp, 2);
        }

        $this->merge([
            'contact_whatsapp' => $whatsapp,
            'contact_email' => mb_strtolower(trim((string) $this->input('contact_email'))),
            'contact_phone' => $this->nullableText('contact_phone'),
            'business_hours_weekdays_open' => $this->nullableText('business_hours_weekdays_open'),
            'business_hours_weekdays_close' => $this->nullableText('business_hours_weekdays_close'),
            'business_hours_saturday_open' => $this->nullableText('business_hours_saturday_open'),
            'business_hours_saturday_close' => $this->nullableText('business_hours_saturday_close'),
            'business_hours_sunday_open' => $this->nullableText('business_hours_sunday_open'),
            'business_hours_sunday_close' => $this->nullableText('business_hours_sunday_close'),
            'pickup_address' => $this->nullableText('pickup_address'),
        ]);
    }

    private function nullableText(string $key): ?string
    {
        $value = Str::squish((string) $this->input($key));

        return $value !== '' ? $value : null;
    }
}
