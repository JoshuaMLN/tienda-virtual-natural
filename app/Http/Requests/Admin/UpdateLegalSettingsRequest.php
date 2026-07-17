<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidPeruvianRuc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateLegalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_trade_name' => ['required', 'string', 'max:80'],
            'legal_provider_name' => ['nullable', 'string', 'max:160'],
            'legal_tax_id' => ['nullable', 'string', new ValidPeruvianRuc],
            'legal_fiscal_address' => ['nullable', 'string', 'max:500'],
            'legal_complaints_book_url' => ['nullable', 'url:http,https', 'max:500'],
            'live_sales_enabled' => ['required', 'boolean'],
            'incident_report_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'refund_processing_business_days' => ['required', 'integer', 'min:1', 'max:30'],
            'delivery_attempts_per_cycle' => ['required', 'integer', 'min:1', 'max:10'],
            'delivery_max_automatic_cycles' => ['required', 'integer', 'min:1', 'max:5'],
            'reshipment_payment_days' => ['required', 'integer', 'min:1', 'max:30'],
            'pickup_hold_days' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'legal_trade_name.required' => 'Ingresa el nombre comercial mostrado al cliente.',
            'legal_provider_name.max' => 'La razon social o nombre del titular no debe superar 160 caracteres.',
            'legal_fiscal_address.max' => 'El domicilio fiscal no debe superar 500 caracteres.',
            'legal_complaints_book_url.url' => 'Ingresa un enlace valido para el Libro de Reclamaciones.',
            'incident_report_hours.min' => 'El aviso preferente debe ser de al menos una hora.',
            'refund_processing_business_days.max' => 'El procesamiento interno no puede superar 30 dias habiles.',
            'delivery_attempts_per_cycle.max' => 'Un ciclo no puede superar 10 intentos.',
            'delivery_max_automatic_cycles.max' => 'No se permiten mas de 5 ciclos automaticos.',
            'reshipment_payment_days.max' => 'El plazo para pagar un reenvio no puede superar 30 dias.',
            'pickup_hold_days.max' => 'El plazo de recojo no puede superar 60 dias.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'legal_trade_name' => Str::squish((string) $this->input('legal_trade_name')),
            'legal_provider_name' => $this->nullableText('legal_provider_name'),
            'legal_tax_id' => $this->digitsOrNull('legal_tax_id'),
            'legal_fiscal_address' => $this->nullableText('legal_fiscal_address'),
            'legal_complaints_book_url' => $this->nullableText('legal_complaints_book_url'),
            'live_sales_enabled' => $this->boolean('live_sales_enabled'),
        ]);
    }

    private function nullableText(string $key): ?string
    {
        $value = Str::squish((string) $this->input($key));

        return $value !== '' ? $value : null;
    }

    private function digitsOrNull(string $key): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $this->input($key)) ?? '';

        return $value !== '' ? $value : null;
    }
}
